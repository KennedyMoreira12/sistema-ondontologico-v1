<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Models\Payment;
use App\Services\Pacientes\PacienteNomeService;
use App\Services\Tenant\TenantRunner;
use Illuminate\Console\Command;

/**
 * Vincula pagamentos importados a pacientes existentes, por revisão assistida.
 *
 * NUNCA cria, renomeia ou funde pacientes. Sem `--apply`, apenas mostra o que
 * faria (dry-run é o padrão). Um vínculo só é gravado quando há um candidato
 * claramente acima dos demais; empates e nomes desconhecidos ficam para a
 * revisão humana na tela de pagamentos.
 */
class VincularPagamentosCommand extends Command
{
    protected $signature = 'financeiro:vincular-pagamentos
        {--clinic= : ID da clínica (padrão: todas as ativas)}
        {--apply : Grava os vínculos; sem esta opção o comando é somente leitura}
        {--min-score=0.97 : Semelhança mínima para vincular automaticamente}
        {--arquivo= : CSV com as colunas payment_id e nome (ou id,nome)}';

    protected $description = 'Sugere e (com --apply) grava o vínculo de pagamentos importados com pacientes';

    public function handle(PacienteNomeService $nomes, TenantRunner $runner): int
    {
        $scoreMinimo = (float) $this->option('min-score');

        if ($scoreMinimo < 0.5 || $scoreMinimo > 1) {
            $this->components->error('--min-score deve estar entre 0.5 e 1.');

            return self::FAILURE;
        }

        $mapa = $this->lerArquivo((string) $this->option('arquivo'));

        if ($mapa === null) {
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

        $aplicar = (bool) $this->option('apply');

        foreach ($clinicas as $clinica) {
            $this->newLine();
            $this->components->info("Clínica: {$clinica->name} (#{$clinica->id})");

            $runner->run($clinica, function (Clinic $clinic) use ($nomes, $mapa, $scoreMinimo, $aplicar): void {
                $this->processarClinica($clinic, $nomes, $mapa, $scoreMinimo, $aplicar);
            });
        }

        if (! $aplicar) {
            $this->newLine();
            $this->components->warn('Dry-run: nada foi gravado. Use --apply para aplicar os vínculos inequívocos.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $mapa
     */
    private function processarClinica(
        Clinic $clinica,
        PacienteNomeService $nomes,
        array $mapa,
        float $scoreMinimo,
        bool $aplicar,
    ): void {
        $pagamentos = Payment::query()
            ->whereNull('paciente_id')
            ->orderBy('id')
            ->get();

        if ($pagamentos->isEmpty()) {
            $this->components->twoColumnDetail('Pagamentos sem vínculo', '0');

            return;
        }

        $linhas = [];
        $vinculados = 0;
        $ambiguos = 0;
        $semNome = 0;
        $semCandidato = 0;

        foreach ($pagamentos as $pagamento) {
            $nome = $mapa[$pagamento->id] ?? $nomes->nomeDoPagamento($pagamento);

            if ($nome === null || trim($nome) === '') {
                $semNome++;

                continue;
            }

            $candidatos = $nomes->candidatos($nome, 5);

            if ($candidatos->isEmpty()) {
                $semCandidato++;
                $linhas[] = [$pagamento->id, $nome, '—', '0', 'sem candidato'];

                continue;
            }

            $melhor = $candidatos->first();
            $segundo = $candidatos->get(1);
            $margem = $segundo === null ? 1.0 : $melhor['score'] - $segundo['score'];
            $inequivoco = $melhor['score'] >= $scoreMinimo && $margem >= 0.02;

            if (! $inequivoco) {
                $ambiguos++;
                $linhas[] = [
                    $pagamento->id,
                    $nome,
                    $melhor['paciente']->nome,
                    number_format($melhor['score'], 3, ',', '.'),
                    'revisar (empate ou score baixo)',
                ];

                continue;
            }

            if ($aplicar) {
                $nomes->vincularPagamento($pagamento, $melhor['paciente']);
                $vinculados++;
            }

            $linhas[] = [
                $pagamento->id,
                $nome,
                $melhor['paciente']->nome,
                number_format($melhor['score'], 3, ',', '.'),
                $aplicar ? 'vinculado' : 'seria vinculado',
            ];
        }

        $this->components->twoColumnDetail('Pagamentos analisados', (string) $pagamentos->count());
        $this->components->twoColumnDetail('Sem nome identificável', (string) $semNome);
        $this->components->twoColumnDetail('Sem candidato', (string) $semCandidato);
        $this->components->twoColumnDetail('Ambíguos (revisão humana)', (string) $ambiguos);
        $this->components->twoColumnDetail($aplicar ? 'Vinculados agora' : 'Seriam vinculados', (string) $vinculados);

        if ($linhas !== []) {
            $this->table(
                ['Pagamento', 'Nome na planilha', 'Paciente sugerido', 'Score', 'Situação'],
                array_slice($linhas, 0, 50),
            );

            if (count($linhas) > 50) {
                $this->components->warn('Mostrando 50 linhas; o restante foi processado igualmente.');
            }
        }
    }

    /**
     * Lê um CSV opcional de apoio (payment_id,nome).
     *
     * @return array<int, string>|null Null quando o arquivo foi pedido e não pôde ser lido.
     */
    private function lerArquivo(string $caminho): ?array
    {
        if ($caminho === '') {
            return [];
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

        $colunaId = array_search('payment_id', $cabecalho, true);
        $colunaId = $colunaId === false ? array_search('id', $cabecalho, true) : $colunaId;
        $colunaNome = array_search('nome', $cabecalho, true);

        if ($colunaId === false || $colunaNome === false) {
            fclose($handle);
            $this->components->error('O CSV precisa ter as colunas payment_id (ou id) e nome.');

            return null;
        }

        $mapa = [];

        while (($linha = fgetcsv($handle, 0, $delimitador, '"', '\\')) !== false) {
            $id = (int) ($linha[$colunaId] ?? 0);
            $nome = trim((string) ($linha[$colunaNome] ?? ''));

            if ($id > 0 && $nome !== '') {
                $mapa[$id] = $nome;
            }
        }

        fclose($handle);

        return $mapa;
    }
}
