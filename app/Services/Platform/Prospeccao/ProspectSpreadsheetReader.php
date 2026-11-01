<?php

namespace App\Services\Platform\Prospeccao;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Lê uma planilha (.xlsx ou .csv) de contatos e devolve as linhas no MESMO
 * formato que o `ProspectImporter` já entende (o formato dos JSON extraídos dos
 * PDFs). Nada aqui grava no banco: a leitura é só a etapa "traduzir arquivo em
 * linhas".
 *
 * - `.xlsx` é lido sem dependência nova: é um zip de XMLs e o PHP já traz
 *   `ZipArchive` + `DOMDocument`. A aba escolhida é a primeira cujo cabeçalho
 *   tenha a coluna do nome/clínica (a planilha real tem uma aba "Como usar"
 *   depois da aba de leads).
 * - `.csv` é lido com `fgetcsv`, com detecção de delimitador e de codificação.
 * - A procedência (`ref`) de cada linha é um hash estável de nome+cidade+
 *   telefone. Reenviar o mesmo arquivo (mesmo com outro nome) reaproveita as
 *   mesmas refs e o importador reconhece como já importado, sem duplicar.
 */
class ProspectSpreadsheetReader
{
    /**
     * Chave de procedência das importações por planilha. É fixa de propósito: a
     * identidade real fica na `ref` de cada linha, então o mesmo contato vindo
     * de arquivos diferentes continua sendo o mesmo registro.
     */
    public const SOURCE_KEY = 'planilha_prospeccao';

    /**
     * Teto de segurança: uma planilha de prospecção comercial não tem milhares
     * de linhas, e o arquivo inteiro fica na sessão entre a prévia e a
     * confirmação.
     */
    private const MAX_ROWS = 2000;

    /**
     * Cabeçalhos aceitos (já normalizados sem acento/maiúscula/pontuação) e o
     * campo canônico de cada um. Só a coluna do nome é obrigatória.
     *
     * @var array<string, string>
     */
    private const CABECALHOS = [
        'cidade' => 'city',
        'municipio' => 'city',

        'clinica profissional' => 'name',
        'clinica' => 'name',
        'nome' => 'name',
        'nome fantasia' => 'name',
        'razao social' => 'name',
        'contato' => 'name',
        'profissional' => 'name',

        'especialidade tipo' => 'category',
        'especialidade' => 'category',
        'tipo' => 'category',
        'categoria' => 'category',
        'segmento' => 'category',

        'telefone publicado' => 'phone',
        'telefone' => 'phone',
        'telefone whatsapp' => 'phone',
        'celular' => 'phone',
        'fone' => 'phone',
        'whatsapp' => 'phone',

        'status do whatsapp' => 'whatsapp',
        'situacao do whatsapp' => 'whatsapp',
        'whatsapp status' => 'whatsapp',

        'endereco' => 'address',
        'logradouro' => 'address',
        'localizacao' => 'address',

        'fonte abrir ficha' => 'source',
        'fonte' => 'source',
        'abrir ficha' => 'source',
        'url' => 'source',
        'link' => 'source',
        'referencia' => 'source',

        'status da prospeccao' => 'status',
        'status prospeccao' => 'status',
        'status' => 'status',
        'etapa' => 'status',

        'data do contato' => 'date',
        'data contato' => 'date',
        'ultimo contato' => 'date',
        'data' => 'date',

        'observacoes' => 'notes',
        'observacao' => 'notes',
        'notas' => 'notes',
        'nota' => 'notes',
    ];

    /**
     * @return array{source_file:string,source_key:string,default_city:?string,default_state:?string,default_category:?string,rows:array<int,array<string,mixed>>,total_linhas:int}
     */
    public function read(string $caminho, string $nomeOriginal): array
    {
        $extensao = strtolower((string) pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        $abas = match ($extensao) {
            'xlsx', 'xlsm' => $this->lerXlsx($caminho),
            'csv' => [$this->lerCsv($caminho)],
            default => throw new ProspectSpreadsheetException('Formato não suportado. Envie um arquivo .xlsx ou .csv.'),
        };

        $matriz = null;
        $indiceCabecalho = 0;
        $mapa = [];

        foreach ($abas as $aba) {
            [$indice, $candidato] = $this->localizarCabecalho($aba);

            if ($indice !== null) {
                $indiceCabecalho = $indice;
                $mapa = $candidato;
                $matriz = $aba;
                break;
            }
        }

        if ($matriz === null) {
            throw new ProspectSpreadsheetException($this->mensagemCabecalhoAusente($abas));
        }

        $cabecalhos = $matriz[$indiceCabecalho];
        $linhas = [];
        $total = 0;

        foreach (array_slice($matriz, $indiceCabecalho + 1) as $linha) {
            $valores = $this->valores($linha, $mapa);

            if ($this->linhaVazia($valores)) {
                continue;
            }

            $total++;

            if ($total > self::MAX_ROWS) {
                throw new ProspectSpreadsheetException('A planilha tem mais de '.self::MAX_ROWS.' contatos. Divida o arquivo em partes menores.');
            }

            $linhas[] = $this->montarLinha($valores, $cabecalhos, $linha);
        }

        if ($linhas === []) {
            throw new ProspectSpreadsheetException('A planilha não tem nenhum contato abaixo do cabeçalho.');
        }

        return [
            'source_file' => $this->nomeDoArquivo($nomeOriginal),
            'source_key' => self::SOURCE_KEY,
            'default_city' => null,
            'default_state' => null,
            'default_category' => null,
            'rows' => $linhas,
            'total_linhas' => $total,
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $valores
     * @param  array<int, string>  $cabecalhos
     * @param  array<int, string>  $linha
     * @return array<string, mixed>
     */
    private function montarLinha(array $valores, array $cabecalhos, array $linha): array
    {
        $nome = (string) ($valores['name'] ?? '');
        $cidade = $this->nulo((string) ($valores['city'] ?? ''));
        $telefone = $this->nulo((string) ($valores['phone'] ?? ''));
        $endereco = $this->nulo((string) ($valores['address'] ?? ''));
        $status = $this->nulo((string) ($valores['status'] ?? ''));

        return [
            'ref' => $this->referencia($nome, $cidade, $telefone, $endereco),
            'name' => $nome,
            'type' => null,
            'category' => $this->nulo((string) ($valores['category'] ?? '')),
            'specialty' => null,
            'phone_original' => $telefone,
            'address' => $endereco,
            'priority' => null,
            'city' => $cidade,
            'status' => $status,
            'notes' => $this->comporNotas(
                $this->nulo((string) ($valores['notes'] ?? '')),
                $this->nulo((string) ($valores['whatsapp'] ?? '')),
                $this->nulo((string) ($valores['source'] ?? '')),
                $status,
            ),
            'last_contact_at' => $this->normalizarData($this->nulo((string) ($valores['date'] ?? ''))),
            'linha_original' => $this->linhaOriginal($cabecalhos, $linha),
        ];
    }

    /**
     * Identidade estável de uma linha, usada como `source_ref`. Reenviar o mesmo
     * contato (mesmo arquivo, outro nome de arquivo, ou planilha reeditada)
     * devolve a mesma ref e não cria duplicata.
     */
    private function referencia(string $nome, ?string $cidade, ?string $telefone, ?string $endereco): string
    {
        $base = $this->normalizar($nome).'|'.$this->normalizar((string) $cidade);

        $digitos = (string) preg_replace('/\D/', '', (string) $telefone);
        $base .= $digitos !== '' ? '|'.$digitos : '|'.$this->normalizar((string) $endereco);

        return substr(sha1($base), 0, 12);
    }

    /**
     * Junta em `notes` o que não tem campo próprio no schema (status do
     * WhatsApp, fonte e status não reconhecido da planilha). O conteúdo cru da
     * linha também vai para a procedência (`linha_original`), então nada some
     * em silêncio.
     */
    private function comporNotas(?string $observacoes, ?string $whatsapp, ?string $fonte, ?string $status): ?string
    {
        $partes = [];

        if ($observacoes !== null) {
            $partes[] = $observacoes;
        }

        if ($whatsapp !== null) {
            $partes[] = 'WhatsApp: '.$whatsapp;
        }

        if ($fonte !== null) {
            $partes[] = 'Fonte: '.$fonte;
        }

        if ($status !== null && ! str_contains($this->normalizar($status), 'nao contatad')) {
            $partes[] = 'Status na planilha: '.$status;
        }

        return $partes === [] ? null : implode("\n", $partes);
    }

    private function normalizarData(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_numeric($valor)) {
            $serie = (int) $valor;

            return $serie > 0 ? Carbon::create(1899, 12, 30)->addDays($serie)->toDateString() : null;
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $cabecalhos
     * @param  array<int, string>  $linha
     * @return array<string, string>
     */
    private function linhaOriginal(array $cabecalhos, array $linha): array
    {
        $original = [];

        foreach ($cabecalhos as $indice => $cabecalho) {
            $rotulo = trim((string) $cabecalho);

            if ($rotulo === '') {
                continue;
            }

            $original[$rotulo] = isset($linha[$indice]) ? trim((string) $linha[$indice]) : '';
        }

        return $original;
    }

    /**
     * @param  array<int, string>  $linha
     * @param  array<string, int>  $mapa
     * @return array<string, string>
     */
    private function valores(array $linha, array $mapa): array
    {
        $valores = [];

        foreach ($mapa as $campo => $indice) {
            $valores[$campo] = isset($linha[$indice]) ? trim((string) $linha[$indice]) : '';
        }

        return $valores;
    }

    /**
     * @param  array<string, string>  $valores
     */
    private function linhaVazia(array $valores): bool
    {
        foreach ($valores as $valor) {
            if ($valor !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Acha a linha de cabeçalho: a primeira (entre as dez primeiras) que tenha a
     * coluna do nome/clínica.
     *
     * @param  array<int, array<int, string>>  $matriz
     * @return array{0:?int,1:array<string,int>}
     */
    private function localizarCabecalho(array $matriz): array
    {
        $limite = min(count($matriz), 10);

        for ($i = 0; $i < $limite; $i++) {
            $mapa = $this->mapaCabecalhos($matriz[$i]);

            if (isset($mapa['name'])) {
                return [$i, $mapa];
            }
        }

        return [null, []];
    }

    /**
     * @param  array<int, string>  $linha
     * @return array<string, int>
     */
    private function mapaCabecalhos(array $linha): array
    {
        $mapa = [];

        foreach ($linha as $indice => $valor) {
            $campo = self::CABECALHOS[$this->normalizar((string) $valor)] ?? null;

            if ($campo !== null && ! isset($mapa[$campo])) {
                $mapa[$campo] = $indice;
            }
        }

        return $mapa;
    }

    /**
     * @param  array<int, array<int, string>>  $abas
     */
    private function mensagemCabecalhoAusente(array $abas): string
    {
        $primeira = [];

        foreach ($abas as $aba) {
            foreach ($aba as $linha) {
                if (! $this->linhaVazia(array_map('strval', $linha))) {
                    $primeira = $linha;
                    break 2;
                }
            }
        }

        $lista = $primeira === [] ? 'nenhum cabeçalho reconhecível' : implode(', ', array_slice($primeira, 0, 8));

        return 'Não encontrei a coluna do nome/clínica na planilha. '
            .'Espero um cabeçalho como "Clínica / profissional" (ou "Nome"). Encontrei: '.$lista.'.';
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function lerXlsx(string $caminho): array
    {
        $zip = new ZipArchive;

        if ($zip->open($caminho) !== true) {
            throw new ProspectSpreadsheetException('Não consegui abrir o arquivo .xlsx. Verifique se ele não está corrompido.');
        }

        try {
            $workbook = $this->carregarXml($zip, 'xl/workbook.xml');

            if ($workbook === null) {
                throw new ProspectSpreadsheetException('O arquivo .xlsx não tem a estrutura esperada.');
            }

            $relacionamentos = [];
            $rels = $this->carregarXml($zip, 'xl/_rels/workbook.xml.rels');

            if ($rels !== null) {
                foreach ($this->buscar($rels, '//*[local-name()="Relationship"]') as $rel) {
                    if (! $rel instanceof DOMElement) {
                        continue;
                    }

                    $relacionamentos[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
                }
            }

            $compartilhadas = $this->carregarSharedStrings($zip);
            $abas = [];
            $nsRelacionamento = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

            foreach ($this->buscar($workbook, '//*[local-name()="sheet"]') as $sheet) {
                if (! $sheet instanceof DOMElement) {
                    continue;
                }

                $target = $relacionamentos[$sheet->getAttributeNS($nsRelacionamento, 'id')] ?? null;

                if ($target === null) {
                    continue;
                }

                $doc = $this->carregarXml($zip, $this->caminhoInterno($target));

                if ($doc === null) {
                    continue;
                }

                $abas[] = $this->matrizDaAba($doc, $compartilhadas);
            }

            return $abas;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function carregarSharedStrings(ZipArchive $zip): array
    {
        $doc = $this->carregarXml($zip, 'xl/sharedStrings.xml');

        if ($doc === null) {
            return [];
        }

        $strings = [];

        foreach ($this->buscar($doc, '//*[local-name()="si"]') as $si) {
            $strings[] = $this->textoInterno($si, 't');
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $compartilhadas
     * @return array<int, array<int, string>>
     */
    private function matrizDaAba(DOMDocument $doc, array $compartilhadas): array
    {
        $matriz = [];

        foreach ($this->buscar($doc, '//*[local-name()="sheetData"]/*[local-name()="row"]') as $linha) {
            $celulas = [];
            $proxima = 0;

            foreach ($this->buscar($doc, './/*[local-name()="c"]', $linha) as $celula) {
                if (! $celula instanceof DOMElement) {
                    continue;
                }

                $indice = $this->coluna($celula->getAttribute('r')) ?? $proxima;
                $celulas[$indice] = $this->valorCelula($celula, $compartilhadas);
                $proxima = $indice + 1;
            }

            if ($celulas !== []) {
                $matriz[] = $this->linhaComLargura($celulas);
            }
        }

        return $matriz;
    }

    /**
     * @param  array<int, string>  $compartilhadas
     */
    private function valorCelula(DOMElement $celula, array $compartilhadas): string
    {
        return match ($celula->getAttribute('t')) {
            'inlineStr' => $this->textoInterno($celula, 't'),
            's' => $this->stringCompartilhada($this->textoInterno($celula, 'v'), $compartilhadas),
            'str' => $this->textoInterno($celula, 'v'),
            'e' => '',
            'b' => $this->textoInterno($celula, 'v') === '1' ? 'Sim' : 'Não',
            default => $this->textoInterno($celula, 'v'),
        };
    }

    /**
     * @param  array<int, string>  $compartilhadas
     */
    private function stringCompartilhada(string $indice, array $compartilhadas): string
    {
        return $indice === '' ? '' : (string) ($compartilhadas[(int) $indice] ?? '');
    }

    /**
     * @param  array<int, string>  $celulas
     * @return array<int, string>
     */
    private function linhaComLargura(array $celulas): array
    {
        $largura = max(array_keys($celulas)) + 1;
        $linha = [];

        for ($i = 0; $i < $largura; $i++) {
            $linha[$i] = isset($celulas[$i]) ? (string) $celulas[$i] : '';
        }

        return $linha;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function lerCsv(string $caminho): array
    {
        $conteudo = @file_get_contents($caminho);

        if ($conteudo === false) {
            throw new ProspectSpreadsheetException('Não consegui ler o arquivo .csv.');
        }

        $conteudo = $this->paraUtf8($conteudo);
        $delimitador = $this->detectarDelimitador(strtok($conteudo, "\r\n") ?: '');

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $conteudo);
        rewind($stream);

        $matriz = [];

        while (($campos = fgetcsv($stream, null, $delimitador, '"', '')) !== false) {
            $matriz[] = array_map(fn ($campo): string => trim((string) $campo), $campos);
        }

        fclose($stream);

        return $matriz;
    }

    private function paraUtf8(string $conteudo): string
    {
        $conteudo = (string) preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }

        return $conteudo;
    }

    private function detectarDelimitador(string $linha): string
    {
        $contagem = [
            ',' => substr_count($linha, ','),
            ';' => substr_count($linha, ';'),
            "\t" => substr_count($linha, "\t"),
        ];

        arsort($contagem);
        $melhor = (string) array_key_first($contagem);

        return $contagem[$melhor] > 0 ? $melhor : ',';
    }

    private function caminhoInterno(string $target): string
    {
        $target = ltrim(str_replace('\\', '/', $target), '/');

        return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
    }

    private function carregarXml(ZipArchive $zip, string $nome): ?DOMDocument
    {
        $indice = $zip->locateName($nome);

        if ($indice === false) {
            return null;
        }

        $conteudo = $zip->getFromIndex($indice);

        if ($conteudo === false || $conteudo === '') {
            return null;
        }

        $doc = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($conteudo);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if (! $ok) {
            throw new ProspectSpreadsheetException('Não consegui interpretar o conteúdo da planilha. O arquivo pode estar corrompido.');
        }

        return $doc;
    }

    /**
     * @return array<int, DOMNode>
     */
    private function buscar(DOMDocument $doc, string $expressao, ?DOMNode $contexto = null): array
    {
        $resultado = (new DOMXPath($doc))->query($expressao, $contexto);

        if ($resultado === false) {
            return [];
        }

        $itens = [];

        foreach ($resultado as $no) {
            $itens[] = $no;
        }

        return $itens;
    }

    /**
     * Concatena o texto dos filhos de um local-name (cobre `inlineStr` e runs de
     * `sharedStrings`).
     */
    private function textoInterno(DOMNode $no, string $local): string
    {
        $doc = $no->ownerDocument;

        if (! $doc instanceof DOMDocument) {
            return '';
        }

        $partes = [];

        foreach ($this->buscar($doc, './/*[local-name()="'.$local.'"]', $no) as $filho) {
            $partes[] = $filho->textContent;
        }

        return implode('', $partes);
    }

    private function coluna(string $referencia): ?int
    {
        $letras = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $referencia));

        if ($letras === '') {
            return null;
        }

        $indice = 0;

        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return $indice - 1;
    }

    private function normalizar(string $valor): string
    {
        $ascii = Str::ascii(trim($valor));
        $espacado = (string) preg_replace('/[^A-Za-z0-9]+/', ' ', $ascii);

        return Str::lower(trim((string) preg_replace('/\s+/', ' ', $espacado)));
    }

    private function nulo(string $valor): ?string
    {
        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }

    private function nomeDoArquivo(string $nomeOriginal): string
    {
        $nome = trim(basename(str_replace('\\', '/', $nomeOriginal)));

        return $nome === '' ? 'planilha' : $nome;
    }
}
