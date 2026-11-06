<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Models\Paciente;
use App\Models\Payment;
use App\Services\Tenant\TenantRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Diagnóstico somente-leitura dos pagamentos importados.
 *
 * Existe por causa da diferença entre 6.174 linhas extraídas da planilha e
 * 6.163 pagamentos no banco. O comando NÃO força as duas contagens a bater:
 * ele mostra totais, distribuição por mês, vínculos ausentes, duplicidades e,
 * quando um CSV da extração é fornecido, exatamente quais chaves sobram de
 * cada lado. Nada é gravado.
 */
class DiagnosticarPagamentosCommand extends Command
{
    protected $signature = 'financeiro:diagnosticar-pagamentos
        {--clinic= : ID da clínica (padrão: todas as ativas)}
        {--arquivo= : CSV da extração com as colunas data e valor (opcional)}';

    protected $description = 'Diagnostica pagamentos importados: totais, vínculos, duplicidades e diferença para a extração';

    public function handle(TenantRunner $runner): int
    {
        $arquivo = (string) $this->option('arquivo');

        /*
         * `--arquivo` é opcional: sem CSV o comando diagnostica só o banco.
         * Só é falha quando um arquivo foi PEDIDO e não pôde ser lido — antes,
         * a ausência da opção era tratada como erro e o comando saía com 1 sem
         * mostrar diagnóstico nenhum.
         */
        $extracao = $this->lerExtracao($arquivo);

        if ($arquivo !== '' && $extracao === null) {
            return self::FAILURE;
        }

        $clinicas = Clinic::query()
            ->when($this->option('clinic'), fn ($query, $id) => $query->whereKey($id))
            ->whereHas('tenantConnection')
            ->with('tenantConnection')
            ->orderBy('id')
            ->get();

        if ($clinicas->isEmpty()) {
            $this->components->warn('Nenhuma clínica com banco configurado.');

            return self::SUCCESS;
        }

        foreach ($clinicas as $clinica) {
            $this->newLine();
            $this->components->info("Clínica: {$clinica->name} (#{$clinica->id})");

            $runner->run($clinica, function () use ($extracao): void {
                $this->diagnosticar($extracao);
            });
        }

        $this->newLine();
        $this->components->warn('Diagnóstico somente leitura: nenhum pagamento foi alterado.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array{chave: string, data: string, valor: float}>|null  $extracao
     */
    private function diagnosticar(?array $extracao): void
    {
        $pagamentos = Payment::query()->orderBy('id')->get();

        $this->components->twoColumnDetail('Pagamentos no banco', (string) $pagamentos->count());
        $this->components->twoColumnDetail('Valor total', number_format((float) $pagamentos->sum('valor'), 2, ',', '.'));
        $this->components->twoColumnDetail('Sem paciente vinculado', (string) $pagamentos->whereNull('paciente_id')->count());
        $this->components->twoColumnDetail('Sem forma de pagamento', (string) $pagamentos->whereNull('payment_method_id')->count());

        $this->distribuicaoMensal($pagamentos);
        $this->vinculosInvalidos($pagamentos);
        $this->duplicidades($pagamentos);

        if ($extracao !== null) {
            $this->compararExtracao($pagamentos, $extracao);
        }
    }

    /**
     * @param  Collection<int, Payment>  $pagamentos
     */
    private function distribuicaoMensal(Collection $pagamentos): void
    {
        $linhas = $pagamentos
            ->groupBy(fn (Payment $pagamento): string => $pagamento->data?->format('Y-m') ?? 'sem data')
            ->map(fn (Collection $grupo, string $mes): array => [
                $mes,
                $grupo->count(),
                number_format((float) $grupo->sum('valor'), 2, ',', '.'),
            ])
            ->sortKeys()
            ->values()
            ->all();

        if ($linhas === []) {
            return;
        }

        $this->newLine();
        $this->components->info('Distribuição mensal');
        $this->table(['Mês', 'Lançamentos', 'Total'], $linhas);
    }

    /**
     * @param  Collection<int, Payment>  $pagamentos
     */
    private function vinculosInvalidos(Collection $pagamentos): void
    {
        $ids = $pagamentos->pluck('paciente_id')->filter()->unique()->values();
        $existentes = Paciente::query()->whereIn('id', $ids)->pluck('id');
        $inexistentes = $ids->diff($existentes);

        $this->newLine();
        $this->components->info('Vínculos');
        $this->components->twoColumnDetail('Pacientes distintos vinculados', (string) $ids->count());
        $this->components->twoColumnDetail('Ids de paciente inexistente', (string) $inexistentes->count());

        if ($inexistentes->isNotEmpty()) {
            $this->components->warn('Ids órfãos: '.$inexistentes->take(20)->implode(', '));
        }
    }

    /**
     * Duplicidades exatas por data, valor e paciente (ou ausência de vínculo).
     *
     * Não são apagadas: duas linhas iguais podem ser dois recebimentos reais.
     * O comando apenas mostra os grupos para conferência com a planilha.
     *
     * @param  Collection<int, Payment>  $pagamentos
     */
    private function duplicidades(Collection $pagamentos): void
    {
        $grupos = $pagamentos
            ->groupBy(fn (Payment $pagamento): string => implode('|', [
                $pagamento->data?->format('Y-m-d') ?? 'sem-data',
                number_format((float) $pagamento->valor, 2, '.', ''),
                $pagamento->paciente_id ?? 'sem-paciente',
                $pagamento->servico ?? 'sem-servico',
            ]))
            ->filter(fn (Collection $grupo): bool => $grupo->count() > 1);

        $this->newLine();
        $this->components->info('Grupos com chave repetida (data + valor + paciente + serviço)');
        $this->components->twoColumnDetail('Grupos repetidos', (string) $grupos->count());
        $this->components->twoColumnDetail('Linhas excedentes', (string) $grupos->sum(fn (Collection $grupo): int => $grupo->count() - 1));

        if ($grupos->isEmpty()) {
            return;
        }

        $linhas = $grupos->take(20)->map(function (Collection $grupo, string $chave): array {
            return [
                $chave,
                $grupo->count(),
                number_format((float) $grupo->sum('valor'), 2, ',', '.'),
                $grupo->pluck('id')->take(10)->implode(', '),
            ];
        })->values()->all();

        $this->table(['Chave', 'Linhas', 'Total', 'Ids'], $linhas);
    }

    /**
     * @param  Collection<int, Payment>  $pagamentos
     * @param  list<array{chave: string, data: string, valor: float}>  $extracao
     */
    private function compararExtracao(Collection $pagamentos, array $extracao): void
    {
        $banco = $pagamentos
            ->map(fn (Payment $pagamento): array => [
                'chave' => ($pagamento->data?->format('Y-m-d') ?? 'sem-data').'|'.number_format((float) $pagamento->valor, 2, '.', ''),
                'data' => $pagamento->data?->format('Y-m-d') ?? 'sem-data',
                'valor' => (float) $pagamento->valor,
            ])
            ->all();

        $contagemBanco = $this->contarPorChave($banco);
        $contagemExtracao = $this->contarPorChave($extracao);

        $soNaExtracao = [];
        $soNoBanco = [];

        foreach ($contagemExtracao as $chave => $quantidade) {
            $diferenca = $quantidade - ($contagemBanco[$chave] ?? 0);

            if ($diferenca > 0) {
                $soNaExtracao[] = [$chave, $diferenca];
            }
        }

        foreach ($contagemBanco as $chave => $quantidade) {
            $diferenca = $quantidade - ($contagemExtracao[$chave] ?? 0);

            if ($diferenca > 0) {
                $soNoBanco[] = [$chave, $diferenca];
            }
        }

        $linhasExtracao = count($extracao);
        $linhasBanco = count($banco);
        $totalExtracao = array_sum(array_column($extracao, 'valor'));
        $totalBanco = array_sum(array_column($banco, 'valor'));

        $this->newLine();
        $this->components->info('Comparação com a extração (CSV)');
        $this->table(['Métrica', 'Extração', 'Banco', 'Diferença'], [
            ['Linhas', $linhasExtracao, $linhasBanco, $linhasExtracao - $linhasBanco],
            ['Valor', number_format($totalExtracao, 2, ',', '.'), number_format($totalBanco, 2, ',', '.'), number_format($totalExtracao - $totalBanco, 2, ',', '.')],
        ]);

        if ($soNaExtracao === [] && $soNoBanco === []) {
            $this->components->info('As chaves data+valor coincidem integralmente.');

            return;
        }

        $this->components->warn('A diferença NÃO foi corrigida automaticamente; confira as chaves abaixo.');

        if ($soNaExtracao !== []) {
            $this->table(['Chave só na extração', 'Linhas'], array_slice($soNaExtracao, 0, 20));
        }

        if ($soNoBanco !== []) {
            $this->table(['Chave só no banco', 'Linhas'], array_slice($soNoBanco, 0, 20));
        }
    }

    /**
     * @param  list<array{chave: string, valor: float}>  $registros
     * @return array<string, int>
     */
    private function contarPorChave(array $registros): array
    {
        $contagem = [];

        foreach ($registros as $registro) {
            $contagem[$registro['chave']] = ($contagem[$registro['chave']] ?? 0) + 1;
        }

        return $contagem;
    }

    /**
     * @return list<array{chave: string, data: string, valor: float}>|null
     */
    private function lerExtracao(string $caminho): ?array
    {
        if ($caminho === '') {
            return null;
        }

        if (! is_file($caminho)) {
            $this->components->error("Arquivo não encontrado: {$caminho}");

            return null;
        }

        $handle = fopen($caminho, 'rb');

        if ($handle === false) {
            $this->components->error("Não foi possível abrir: {$caminho}");

            return null;
        }

        $primeira = fgets($handle) ?: '';
        $delimitador = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';
        rewind($handle);

        $cabecalho = fgetcsv($handle, 0, $delimitador, '"', '\\') ?: [];
        $cabecalho = array_map(fn ($valor): string => strtolower(trim((string) $valor)), $cabecalho);
        $colunaData = array_search('data', $cabecalho, true);
        $colunaValor = array_search('valor', $cabecalho, true);

        if ($colunaData === false || $colunaValor === false) {
            fclose($handle);
            $this->components->error('O CSV da extração precisa ter as colunas data e valor.');

            return null;
        }

        $registros = [];

        while (($linha = fgetcsv($handle, 0, $delimitador, '"', '\\')) !== false) {
            $data = trim((string) ($linha[$colunaData] ?? ''));
            $valor = $this->normalizarValor($linha[$colunaValor] ?? null);

            if ($data === '' || $valor === null) {
                continue;
            }

            $dataNormalizada = $this->normalizarData($data);

            $registros[] = [
                'chave' => $dataNormalizada.'|'.number_format($valor, 2, '.', ''),
                'data' => $dataNormalizada,
                'valor' => $valor,
            ];
        }

        fclose($handle);

        return $registros;
    }

    private function normalizarData(string $data): string
    {
        $data = trim($data);

        foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y'] as $formato) {
            $convertida = \DateTimeImmutable::createFromFormat($formato, $data);

            if ($convertida !== false) {
                return $convertida->format('Y-m-d');
            }
        }

        return $data;
    }

    private function normalizarValor(mixed $valor): ?float
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        $texto = str_replace(['R$', ' ', "\u{00A0}"], '', (string) $valor);

        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
        }

        $texto = str_replace(',', '.', $texto);

        return is_numeric($texto) ? round((float) $texto, 2) : null;
    }
}
