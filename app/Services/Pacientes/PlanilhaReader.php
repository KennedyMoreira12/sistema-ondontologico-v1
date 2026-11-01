<?php

namespace App\Services\Pacientes;

use DateTimeImmutable;
use SimpleXMLElement;
use ZipArchive;

/**
 * Leitor de planilhas .xlsx e .csv sem dependência externa.
 *
 * A clínica manda planilha feita no Excel, LibreOffice ou Google Sheets. Em vez
 * de instalar PhpSpreadsheet só para isso, o .xlsx é lido como o que ele é: um
 * ZIP de XMLs. O CSV é lido com fgetcsv, com detecção de delimitador e de
 * codificação (Windows-1252 é o padrão do Excel em português).
 *
 * A saída é sempre a mesma: o cabeçalho (primeira linha com conteúdo) e as
 * linhas de dados já alinhadas com ele, com texto limpo e sem linha vazia.
 */
final class PlanilhaReader
{
    public const MAX_LINHAS = 3000;

    public const MAX_COLUNAS = 60;

    /**
     * @return array{cabecalho: list<string>, linhas: list<list<string>>, truncada: bool}
     */
    public function ler(string $caminho, string $extensao): array
    {
        $extensao = strtolower(ltrim($extensao, '.'));

        if (! is_file($caminho) || ! is_readable($caminho)) {
            throw new PlanilhaException('Não foi possível abrir o arquivo enviado. Envie novamente.');
        }

        $tabela = match ($extensao) {
            'xlsx' => $this->lerXlsx($caminho),
            'csv', 'txt' => $this->lerCsv($caminho),
            default => throw new PlanilhaException('Formato não suportado. Envie a planilha em .xlsx ou .csv.'),
        };

        return $this->normalizar($tabela);
    }

    /**
     * @return list<array<int, string>>
     */
    private function lerXlsx(string $caminho): array
    {
        $zip = new ZipArchive;

        if ($zip->open($caminho) !== true) {
            throw new PlanilhaException('Não foi possível abrir o .xlsx. O arquivo pode estar corrompido ou protegido por senha.');
        }

        try {
            $compartilhadas = $this->lerTextosCompartilhados($zip);
            $estilos = $this->lerEstilos($zip);
            $conteudo = $zip->getFromName($this->primeiraAba($zip));

            if ($conteudo === false) {
                throw new PlanilhaException('A planilha não tem nenhuma aba legível.');
            }

            return $this->lerAba($conteudo, $compartilhadas, $estilos);
        } finally {
            $zip->close();
        }
    }

    /**
     * A primeira aba é resolvida pelo workbook porque o nome do XML interno
     * (`sheet1.xml`, `sheet2.xml`...) não acompanha a ordem das abas no Excel.
     */
    private function primeiraAba(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relacoes = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $relacoes !== false) {
            $xml = $this->xml($workbook);
            $xmlRelacoes = $this->xml($relacoes);

            if ($xml !== null && $xmlRelacoes !== null) {
                $abas = $xml->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [];
                $primeira = $abas[0] ?? null;

                if ($primeira !== null) {
                    $atributos = $primeira->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                    $relId = (string) ($atributos['id'] ?? '');

                    foreach ($xmlRelacoes->xpath('//*[local-name()="Relationship"]') ?: [] as $relacao) {
                        if ((string) $relacao['Id'] !== $relId) {
                            continue;
                        }

                        $alvo = ltrim((string) $relacao['Target'], '/');
                        $caminho = str_starts_with($alvo, 'xl/') ? $alvo : 'xl/'.$alvo;

                        if ($zip->locateName($caminho) !== false) {
                            return $caminho;
                        }
                    }
                }
            }
        }

        if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $abas = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nome = $zip->getNameIndex($i);

            if (is_string($nome) && str_starts_with($nome, 'xl/worksheets/') && str_ends_with($nome, '.xml')) {
                $abas[] = $nome;
            }
        }

        sort($abas);

        if ($abas === []) {
            throw new PlanilhaException('A planilha não tem nenhuma aba legível.');
        }

        return $abas[0];
    }

    /**
     * @return list<string>
     */
    private function lerTextosCompartilhados(ZipArchive $zip): array
    {
        $conteudo = $zip->getFromName('xl/sharedStrings.xml');

        if ($conteudo === false) {
            return [];
        }

        $xml = $this->xml($conteudo);

        if ($xml === null) {
            return [];
        }

        $textos = [];

        foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $partes = [];

            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $texto) {
                $partes[] = (string) $texto;
            }

            $textos[] = implode('', $partes);
        }

        return $textos;
    }

    /**
     * Descobre quais estilos de célula são datas, para converter o serial do
     * Excel no texto que o usuário vê (`dd/mm/aaaa`). Sem isso, uma coluna de
     * nascimento chegaria como "31234".
     *
     * @return list<bool>
     */
    private function lerEstilos(ZipArchive $zip): array
    {
        $conteudo = $zip->getFromName('xl/styles.xml');

        if ($conteudo === false) {
            return [];
        }

        $xml = $this->xml($conteudo);

        if ($xml === null) {
            return [];
        }

        $formatos = [];

        foreach ($xml->xpath('//*[local-name()="numFmts"]/*[local-name()="numFmt"]') ?: [] as $formato) {
            $formatos[(int) $formato['numFmtId']] = (string) $formato['formatCode'];
        }

        $estilos = [];

        foreach ($xml->xpath('//*[local-name()="cellXfs"]/*[local-name()="xf"]') ?: [] as $estilo) {
            $id = (int) $estilo['numFmtId'];
            $estilos[] = $this->formatoEhData($id, $formatos[$id] ?? '');
        }

        return $estilos;
    }

    private function formatoEhData(int $id, string $codigo): bool
    {
        if (($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47)) {
            return true;
        }

        if ($codigo === '') {
            return false;
        }

        // Tira literais ("de") e seções ([Red], [$-pt-BR]) antes de procurar
        // letras de data/hora no formato.
        $codigo = preg_replace('/"[^"]*"/', '', $codigo) ?? $codigo;
        $codigo = preg_replace('/\[[^\]]*\]/', '', $codigo) ?? $codigo;

        return preg_match('/[ymdhs]/i', $codigo) === 1;
    }

    private function serialParaData(float $serial): string
    {
        $dias = (int) floor($serial);

        if ($dias < 1 || $dias > 2958465) {
            return '';
        }

        return (new DateTimeImmutable('1899-12-30'))->modify("+{$dias} days")->format('d/m/Y');
    }

    /**
     * @param  list<string>  $compartilhadas
     * @param  list<bool>  $estilos
     * @return list<array<int, string>>
     */
    private function lerAba(string $conteudo, array $compartilhadas, array $estilos): array
    {
        $xml = $this->xml($conteudo);

        if ($xml === null) {
            throw new PlanilhaException('Não foi possível interpretar o conteúdo da planilha.');
        }

        $linhas = [];
        $limite = self::MAX_LINHAS + 200; // margem para cabeçalho e linhas em branco

        foreach ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $linha) {
            $celulas = [];
            $indice = 0;

            foreach ($linha->xpath('*[local-name()="c"]') ?: [] as $celula) {
                $referencia = (string) $celula['r'];

                if ($referencia !== '' && preg_match('/^([A-Z]+)/i', $referencia, $partes) === 1) {
                    $indice = $this->indiceColuna(strtoupper($partes[1]));
                }

                $tipo = (string) $celula['t'];
                $valor = '';

                if ($tipo === 'inlineStr') {
                    $textos = [];

                    foreach ($celula->xpath('.//*[local-name()="t"]') ?: [] as $texto) {
                        $textos[] = (string) $texto;
                    }

                    $valor = implode('', $textos);
                } else {
                    $valores = $celula->xpath('*[local-name()="v"]');
                    $valor = $valores !== [] ? (string) $valores[0] : '';

                    if ($tipo === 's') {
                        $valor = $compartilhadas[(int) $valor] ?? '';
                    } elseif ($valor !== '' && is_numeric($valor) && ($estilos[(int) $celula['s']] ?? false)) {
                        // Célula numérica formatada como data: o XML guarda o
                        // serial do Excel, não o texto que aparece na tela.
                        $valor = $this->serialParaData((float) $valor);
                    }
                }

                if ($indice < self::MAX_COLUNAS) {
                    $celulas[$indice] = $this->limparTexto($valor);
                }

                $indice++;
            }

            if ($celulas !== []) {
                ksort($celulas);
                $linhas[] = $celulas;
            }

            if (count($linhas) >= $limite) {
                break;
            }
        }

        return $linhas;
    }

    /**
     * @return list<array<int, string>>
     */
    private function lerCsv(string $caminho): array
    {
        $conteudo = file_get_contents($caminho);

        if ($conteudo === false || trim($conteudo) === '') {
            throw new PlanilhaException('O arquivo está vazio.');
        }

        $conteudo = $this->converterParaUtf8($conteudo);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;
        $delimitador = $this->detectarDelimitador($conteudo);

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new PlanilhaException('Não foi possível ler o arquivo enviado.');
        }

        fwrite($stream, $conteudo);
        rewind($stream);

        $linhas = [];
        $limite = self::MAX_LINHAS + 200;

        try {
            while (($linha = fgetcsv($stream, 0, $delimitador, '"', '')) !== false) {
                $linhas[] = array_map(
                    fn ($valor): string => $this->limparTexto((string) $valor),
                    array_slice($linha, 0, self::MAX_COLUNAS),
                );

                if (count($linhas) >= $limite) {
                    break;
                }
            }
        } finally {
            fclose($stream);
        }

        return $linhas;
    }

    private function detectarDelimitador(string $conteudo): string
    {
        $linhas = array_slice(preg_split('/\R/u', mb_substr($conteudo, 0, 20000)) ?: [], 0, 10);
        $melhor = ';';
        $melhorPontuacao = 0;

        foreach ([';', ',', "\t", '|'] as $candidato) {
            $contagens = [];

            foreach ($linhas as $linha) {
                if (trim($linha) === '') {
                    continue;
                }

                $contagens[] = substr_count($linha, $candidato);
            }

            if ($contagens === [] || max($contagens) === 0) {
                continue;
            }

            $frequencias = array_count_values($contagens);
            arsort($frequencias);
            $moda = (int) array_key_first($frequencias);
            $repeticao = (int) reset($frequencias);
            $pontuacao = $moda > 0 ? $repeticao * 100 + $moda : 0;

            if ($pontuacao > $melhorPontuacao) {
                $melhor = $candidato;
                $melhorPontuacao = $pontuacao;
            }
        }

        return $melhor;
    }

    private function converterParaUtf8(string $conteudo): string
    {
        if (mb_check_encoding($conteudo, 'UTF-8')) {
            return $conteudo;
        }

        $convertido = @mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');

        if (is_string($convertido) && mb_check_encoding($convertido, 'UTF-8')) {
            return $convertido;
        }

        $convertido = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $conteudo);

        if (is_string($convertido) && $convertido !== '') {
            return $convertido;
        }

        throw new PlanilhaException('Não foi possível identificar a codificação do CSV. Salve o arquivo em UTF-8 e envie novamente.');
    }

    /**
     * @param  list<array<int, string>>  $tabela
     * @return array{cabecalho: list<string>, linhas: list<list<string>>, truncada: bool}
     */
    private function normalizar(array $tabela): array
    {
        $tabela = array_values(array_filter(
            $tabela,
            fn (array $linha): bool => array_filter($linha, fn ($valor): bool => trim((string) $valor) !== '') !== [],
        ));

        if ($tabela === []) {
            throw new PlanilhaException('A planilha está vazia.');
        }

        // Planilha de clínica às vezes vem com uma linha de título ("Pacientes
        // - outubro") antes do cabeçalho. O cabeçalho é a primeira linha que
        // tem pelo menos metade das colunas da linha mais larga.
        $indiceCabecalho = $this->indiceCabecalho($tabela);
        $cabecalhoBruto = $tabela[$indiceCabecalho] ?? [];
        $tabela = array_slice($tabela, $indiceCabecalho + 1);
        ksort($cabecalhoBruto);

        $ultimaColuna = -1;

        foreach ($cabecalhoBruto as $indice => $valor) {
            if (trim((string) $valor) !== '') {
                $ultimaColuna = max($ultimaColuna, (int) $indice);
            }
        }

        if ($ultimaColuna < 0) {
            throw new PlanilhaException('A primeira linha da planilha precisa ter o nome das colunas.');
        }

        $ultimaColuna = min($ultimaColuna, self::MAX_COLUNAS - 1);
        $cabecalho = [];

        for ($indice = 0; $indice <= $ultimaColuna; $indice++) {
            $rotulo = $this->limparTexto((string) ($cabecalhoBruto[$indice] ?? ''));
            $cabecalho[] = $rotulo !== '' ? $rotulo : 'Coluna '.$this->letraColuna($indice);
        }

        $cabecalho = $this->rotulosUnicos($cabecalho);
        $truncada = count($tabela) > self::MAX_LINHAS;
        $linhas = [];

        foreach (array_slice($tabela, 0, self::MAX_LINHAS) as $linhaBruta) {
            $linha = [];

            for ($indice = 0; $indice <= $ultimaColuna; $indice++) {
                $linha[] = $this->limparTexto((string) ($linhaBruta[$indice] ?? ''));
            }

            if (array_filter($linha, fn (string $valor): bool => $valor !== '') === []) {
                continue;
            }

            $linhas[] = $linha;
        }

        if ($linhas === []) {
            throw new PlanilhaException('A planilha tem cabeçalho, mas nenhuma linha de dados abaixo dele.');
        }

        return ['cabecalho' => $cabecalho, 'linhas' => $linhas, 'truncada' => $truncada];
    }

    /**
     * @param  list<array<int, string>>  $linhas
     */
    private function indiceCabecalho(array $linhas): int
    {
        $amostra = array_slice($linhas, 0, 5);
        $maior = 0;

        foreach ($amostra as $linha) {
            $maior = max($maior, count($linha));
        }

        $minimo = max(2, (int) ceil($maior / 2));

        foreach ($amostra as $indice => $linha) {
            if (count($linha) >= $minimo) {
                return $indice;
            }
        }

        return 0;
    }

    /**
     * @param  list<string>  $rotulos
     * @return list<string>
     */
    private function rotulosUnicos(array $rotulos): array
    {
        $vistos = [];

        foreach ($rotulos as $indice => $rotulo) {
            $chave = mb_strtolower($rotulo);
            $vistos[$chave] = ($vistos[$chave] ?? 0) + 1;

            if ($vistos[$chave] > 1) {
                $rotulos[$indice] = $rotulo.' ('.$vistos[$chave].')';
            }
        }

        return $rotulos;
    }

    private function letraColuna(int $indice): string
    {
        $letras = '';
        $indice++;

        while ($indice > 0) {
            $letras = chr(65 + (($indice - 1) % 26)).$letras;
            $indice = intdiv($indice - 1, 26);
        }

        return $letras;
    }

    private function indiceColuna(string $letras): int
    {
        $indice = 0;

        for ($i = 0, $tamanho = strlen($letras); $i < $tamanho; $i++) {
            $indice = $indice * 26 + (ord($letras[$i]) - 64);
        }

        return max(0, $indice - 1);
    }

    private function limparTexto(string $valor): string
    {
        $valor = str_replace(["\u{00A0}", "\u{FEFF}"], ' ', $valor);
        $valor = preg_replace('/\s+/u', ' ', $valor) ?? $valor;

        return trim($valor);
    }

    private function xml(string $conteudo): ?SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($conteudo, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }

        return $xml === false ? null : $xml;
    }
}
