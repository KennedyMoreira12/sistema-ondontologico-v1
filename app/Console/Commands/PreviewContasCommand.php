<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use ZipArchive;

/**
 * Prévia somente-leitura do CONTAS.xlsx.
 *
 * A planilha CONTAS tem dezenas de abas mensais, com as colunas TIPO,
 * FAVORECIDO, VENCIMENTO, VALOR e — a partir de OUTUBRO 2023 — JUROS. Antes de
 * qualquer importação é preciso saber o que o arquivo contém: quantas abas,
 * quantas linhas de dados, quanto somam, quantas linhas têm juros e onde há
 * duplicidade.
 *
 * Este comando NÃO grava nada no banco. Ele lê o .xlsx direto (ZIP + XML) e
 * imprime o relatório. Rodar duas vezes produz exatamente a mesma saída, porque
 * não há escrita e nenhuma decisão automática sobre o que importar.
 */
class PreviewContasCommand extends Command
{
    protected $signature = 'financeiro:preview-contas
        {arquivo : Caminho do arquivo CONTAS.xlsx (o arquivo não fica no repositório)}
        {--aba=* : Abas específicas a conferir (pode repetir; padrão: todas)}';

    protected $description = 'Mostra a prévia do CONTAS.xlsx (abas, linhas, valores, juros e duplicidades) sem gravar nada no banco.';

    /**
     * Nomes das colunas que o relatório tenta localizar. A comparação é feita
     * sem acentos, sem caixa e sem espaços repetidos.
     *
     * @var array<string, list<string>>
     */
    private const COLUNAS = [
        'vencimento' => ['VENCIMENTO', 'DATA VENCIMENTO', 'DATA DE VENCIMENTO', 'DATA'],
        'valor' => ['VALOR', 'VALOR DA CONTA', 'VALOR CONTA'],
        'juros' => ['JUROS', 'JUROS E MULTA', 'JUROS/MULTA'],
        'favorecido' => ['FAVORECIDO', 'FORNECEDOR', 'PROFISSIONAL'],
        'tipo' => ['TIPO', 'CATEGORIA'],
        'conta' => ['CONTA', 'NOME DA CONTA', 'DESCRICAO', 'DESCRIÇÃO'],
    ];

    public function handle(): int
    {
        $arquivo = (string) $this->argument('arquivo');

        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        $zip = new ZipArchive;

        if ($zip->open($arquivo) !== true) {
            $this->error('Não foi possível abrir o arquivo como .xlsx (ZIP inválido).');

            return self::FAILURE;
        }

        $sharedStrings = $this->lerSharedStrings($zip);
        $abas = $this->lerAbas($zip);

        if ($abas === []) {
            $zip->close();
            $this->error('Nenhuma aba encontrada no arquivo.');

            return self::FAILURE;
        }

        $filtro = array_map(fn (string $aba) => $this->normalizar($aba), (array) $this->option('aba'));
        $abas = array_values(array_filter(
            $abas,
            fn (array $aba) => $filtro === [] || in_array($this->normalizar($aba['nome']), $filtro, true),
        ));

        if ($abas === []) {
            $zip->close();
            $this->error('Nenhuma aba corresponde ao filtro informado.');

            return self::FAILURE;
        }

        $resumo = [];
        $detalhesDuplicadas = [];
        $linhasTotais = 0;
        $valorTotal = 0.0;
        $linhasComJuros = 0;
        $jurosTotal = 0.0;
        $duplicadasTotais = 0;

        foreach ($abas as $aba) {
            $linhas = $this->lerLinhas($zip, $aba['caminho'], $sharedStrings);
            $analise = $this->analisarAba($linhas);

            $resumo[] = [
                $aba['nome'],
                $analise['linhas'],
                'R$ '.number_format($analise['valor'], 2, ',', '.'),
                $analise['linhas_com_juros'],
                'R$ '.number_format($analise['juros'], 2, ',', '.'),
                $analise['duplicadas'],
            ];

            $linhasTotais += $analise['linhas'];
            $valorTotal += $analise['valor'];
            $linhasComJuros += $analise['linhas_com_juros'];
            $jurosTotal += $analise['juros'];
            $duplicadasTotais += $analise['duplicadas'];

            foreach ($analise['detalhes_duplicadas'] as $chave => $quantidade) {
                $detalhesDuplicadas[] = [$aba['nome'], $chave, $quantidade];
            }
        }

        $zip->close();

        $this->newLine();
        $this->info('Prévia do CONTAS.xlsx — somente leitura');
        $this->line('Arquivo: '.$arquivo);
        $this->line('Abas analisadas: '.count($abas));
        $this->newLine();

        $this->table(
            ['Aba', 'Linhas de dados', 'Valor total', 'Linhas com juros', 'Total juros', 'Duplicidades'],
            $resumo,
        );

        $this->table(
            ['Resumo geral', 'Valor'],
            [
                ['Abas', count($abas)],
                ['Linhas de dados', $linhasTotais],
                ['Valor total', 'R$ '.number_format($valorTotal, 2, ',', '.')],
                ['Linhas com juros', $linhasComJuros],
                ['Total de juros', 'R$ '.number_format($jurosTotal, 2, ',', '.')],
                ['Duplicidades (vencimento + valor + favorecido)', $duplicadasTotais],
            ],
        );

        if ($detalhesDuplicadas !== []) {
            $this->warn('Duplicidades encontradas (mesma aba, mesmo vencimento, mesmo valor e mesmo favorecido):');

            $this->table(
                ['Aba', 'Chave', 'Ocorrências'],
                array_slice($detalhesDuplicadas, 0, 50),
            );

            if (count($detalhesDuplicadas) > 50) {
                $this->line('... e mais '.(count($detalhesDuplicadas) - 50).' chave(s).');
            }
        }

        $this->newLine();
        $this->comment('Nenhum dado foi gravado. Nenhuma decisão de importação foi tomada.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function lerSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            return [];
        }

        $strings = [];

        foreach ($doc->si as $si) {
            // Uma string compartilhada pode ter runs (<r><t>) além do <t> direto.
            if (isset($si->t)) {
                $strings[] = (string) $si->t;

                continue;
            }

            $texto = '';

            foreach ($si->r as $run) {
                $texto .= (string) $run->t;
            }

            $strings[] = $texto;
        }

        return $strings;
    }

    /**
     * @return list<array{nome: string, caminho: string}>
     */
    private function lerAbas(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return [];
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        if ($workbook === false || $rels === false) {
            return [];
        }

        $mapaRels = [];

        foreach ($rels->Relationship as $rel) {
            $mapaRels[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $namespaceRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $abas = [];

        foreach ($workbook->sheets->sheet as $sheet) {
            $atributos = $sheet->attributes($namespaceRel);
            $rid = (string) ($atributos['id'] ?? '');
            $target = str_replace('\\', '/', $mapaRels[$rid] ?? '');

            if ($target === '') {
                continue;
            }

            if (str_starts_with($target, '/')) {
                $target = ltrim($target, '/');
            } elseif (! str_starts_with($target, 'xl/')) {
                $target = 'xl/'.$target;
            }

            $abas[] = ['nome' => (string) $sheet['name'], 'caminho' => $target];
        }

        return $abas;
    }

    /**
     * @param  array<int, array<int, ?string>>  $linhas
     * @return array{linhas: int, valor: float, linhas_com_juros: int, juros: float, duplicadas: int, detalhes_duplicadas: array<string, int>}
     */
    private function analisarAba(array $linhas): array
    {
        $linhas = array_filter($linhas, fn (array $celulas) => $this->linhaTemConteudo($celulas));

        if ($linhas === []) {
            return [
                'linhas' => 0,
                'valor' => 0.0,
                'linhas_com_juros' => 0,
                'juros' => 0.0,
                'duplicadas' => 0,
                'detalhes_duplicadas' => [],
            ];
        }

        ksort($linhas);
        $numeros = array_keys($linhas);
        $indiceCabecalho = $this->acharCabecalho($linhas, $numeros);
        $cabecalhos = $this->mapearCabecalhos($linhas[$indiceCabecalho] ?? []);

        $colVencimento = $this->acharColuna($cabecalhos, self::COLUNAS['vencimento']);
        $colValor = $this->acharColuna($cabecalhos, self::COLUNAS['valor']);
        $colJuros = $this->acharColuna($cabecalhos, self::COLUNAS['juros']);
        $colFavorecido = $this->acharColuna($cabecalhos, self::COLUNAS['favorecido']);

        $totalLinhas = 0;
        $valor = 0.0;
        $linhasComJuros = 0;
        $juros = 0.0;
        $chaves = [];

        foreach ($numeros as $numero) {
            if ($numero <= $indiceCabecalho) {
                continue;
            }

            $celulas = $linhas[$numero];

            if (! $this->linhaTemConteudo($celulas)) {
                continue;
            }

            $totalLinhas++;

            $valorLinha = $colValor !== null ? $this->numero($celulas[$colValor] ?? null) : null;
            $valor += $valorLinha ?? 0.0;

            $jurosLinha = $colJuros !== null ? $this->numero($celulas[$colJuros] ?? null) : null;

            if ($jurosLinha !== null && $jurosLinha > 0) {
                $linhasComJuros++;
                $juros += $jurosLinha;
            }

            if ($colVencimento !== null && $valorLinha !== null) {
                $vencimento = $this->data($celulas[$colVencimento] ?? null);
                $favorecido = $colFavorecido !== null
                    ? $this->normalizar($celulas[$colFavorecido] ?? '')
                    : '';

                $chave = ($vencimento ?? 'sem-data')
                    .' | '.number_format($valorLinha, 2, '.', '')
                    .' | '.$favorecido;

                $chaves[$chave] = ($chaves[$chave] ?? 0) + 1;
            }
        }

        $detalhes = array_filter($chaves, fn (int $quantidade) => $quantidade > 1);

        return [
            'linhas' => $totalLinhas,
            'valor' => $valor,
            'linhas_com_juros' => $linhasComJuros,
            'juros' => $juros,
            'duplicadas' => count($detalhes),
            'detalhes_duplicadas' => $detalhes,
        ];
    }

    /**
     * @param  array<int, array<int, ?string>>  $linhas
     * @param  list<int>  $numeros
     */
    private function acharCabecalho(array $linhas, array $numeros): int
    {
        $palavras = ['VALOR', 'VENCIMENTO', 'FAVORECIDO', 'JUROS', 'TIPO'];

        foreach ($numeros as $numero) {
            foreach ($linhas[$numero] as $celula) {
                $normalizada = $this->normalizar($celula ?? '');

                foreach ($palavras as $palavra) {
                    if ($normalizada === $palavra || str_contains($normalizada, $palavra)) {
                        return $numero;
                    }
                }
            }
        }

        return $numeros[0];
    }

    /**
     * @param  array<int, ?string>  $celulas
     * @return array<string, int>
     */
    private function mapearCabecalhos(array $celulas): array
    {
        $cabecalhos = [];

        foreach ($celulas as $coluna => $texto) {
            $normalizado = $this->normalizar($texto ?? '');

            if ($normalizado !== '' && ! isset($cabecalhos[$normalizado])) {
                $cabecalhos[$normalizado] = $coluna;
            }
        }

        return $cabecalhos;
    }

    /**
     * @param  array<string, int>  $cabecalhos
     * @param  list<string>  $aliases
     */
    private function acharColuna(array $cabecalhos, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            if (isset($cabecalhos[$alias])) {
                return $cabecalhos[$alias];
            }
        }

        return null;
    }

    /**
     * @param  array<int, ?string>  $celulas
     */
    private function linhaTemConteudo(array $celulas): bool
    {
        foreach ($celulas as $celula) {
            if ($celula !== null && trim($celula) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Converte o texto da célula em número. Aceita tanto o número cru do Excel
     * quanto texto no formato brasileiro ("1.500,00").
     */
    private function numero(?string $valor): ?float
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $limpo = preg_replace('/[^\d,.\-]/', '', $valor) ?? '';

        if ($limpo === '' || $limpo === '-') {
            return null;
        }

        if (str_contains($limpo, ',')) {
            $limpo = str_replace('.', '', $limpo);
            $limpo = str_replace(',', '.', $limpo);
        }

        return is_numeric($limpo) ? (float) $limpo : null;
    }

    /**
     * Converte a data. Quando a célula é um serial do Excel (número entre 1 e
     * 60000), usa a época 1899-12-30; quando é texto, devolve os dígitos como
     * estão para servir de chave de duplicidade.
     */
    private function data(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $valor = trim($valor);

        if (is_numeric($valor)) {
            $serial = (float) $valor;

            if ($serial >= 1 && $serial <= 60000) {
                return Carbon::create(1899, 12, 30)
                    ->addDays((int) floor($serial))
                    ->toDateString();
            }
        }

        return $this->normalizar($valor);
    }

    private function normalizar(?string $valor): string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return '';
        }

        $semAcento = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);

        if ($semAcento === false) {
            $semAcento = $valor;
        }

        $semAcento = strtoupper($semAcento);
        $semAcento = preg_replace('/[^A-Z0-9\/]+/', ' ', $semAcento) ?? '';

        return trim(preg_replace('/\s+/', ' ', $semAcento) ?? '');
    }

    private function colunaParaIndice(string $letras): int
    {
        $indice = 0;

        foreach (str_split(strtoupper($letras)) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return max(0, $indice - 1);
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array<int, array<int, ?string>>
     */
    private function lerLinhas(ZipArchive $zip, string $caminho, array $sharedStrings): array
    {
        $xml = $zip->getFromName($caminho);

        if ($xml === false) {
            return [];
        }

        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            return [];
        }

        $linhas = [];

        foreach ($doc->sheetData->row as $row) {
            $numero = (int) $row['r'];
            $celulas = [];

            foreach ($row->c as $celula) {
                $referencia = (string) $celula['r'];
                preg_match('/^([A-Z]+)/', strtoupper($referencia), $partes);
                $coluna = $this->colunaParaIndice($partes[1] ?? 'A');
                $tipo = (string) $celula['t'];

                if ($tipo === 's') {
                    $indice = (int) $celula->v;
                    $celulas[$coluna] = $sharedStrings[$indice] ?? null;
                } elseif ($tipo === 'inlineStr') {
                    $celulas[$coluna] = (string) ($celula->is->t ?? '');
                } elseif ($tipo === 'str') {
                    $celulas[$coluna] = (string) $celula->v;
                } else {
                    $celulas[$coluna] = isset($celula->v) ? (string) $celula->v : null;
                }
            }

            $linhas[$numero] = $celulas;
        }

        return $linhas;
    }
}
