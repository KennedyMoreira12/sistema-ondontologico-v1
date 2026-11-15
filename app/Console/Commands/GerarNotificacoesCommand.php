<?php

namespace App\Console\Commands;

use App\Jobs\GerarNotificacoesDaClinica;
use App\Models\Central\Clinic;
use App\Services\Notificacoes\GeradorNotificacoes;
use App\Services\Tenant\TenantRunner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Recalcula as notificações de todas as clínicas ativas.
 *
 * O padrão é enfileirar um job por clínica, para que a varredura de uma
 * clínica grande não atrase a das outras. Com `QUEUE_CONNECTION=sync` o job
 * roda na hora; quando houver worker de verdade, a mesma chamada passa a
 * rodar em segundo plano sem mudar o comando.
 *
 * `--sync` existe para diagnóstico e para testes: executa aqui mesmo e mostra
 * quantos alertas cada categoria tem agora.
 */
class GerarNotificacoesCommand extends Command
{
    protected $signature = 'notificacoes:gerar
        {--clinic= : ID de uma clínica específica (padrão: todas)}
        {--sync : Executa a varredura na hora, sem enfileirar}';

    protected $description = 'Gera notificações a partir do estado atual das clínicas';

    public function handle(TenantRunner $runner, GeradorNotificacoes $gerador): int
    {
        $clinicas = Clinic::query()
            ->where('is_active', true)
            ->whereHas('tenantConnection', fn ($query) => $query->where('is_active', true))
            ->when($this->option('clinic'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($clinicas->isEmpty()) {
            $this->components->warn('Nenhuma clínica ativa com banco configurado.');

            return self::SUCCESS;
        }

        $falhas = [];

        foreach ($clinicas as $clinica) {
            try {
                if ($this->option('sync')) {
                    $resultado = $runner->run($clinica, fn (Clinic $clinic): array => $gerador->gerar());

                    $resumo = collect($resultado)
                        ->map(fn (int $quantidade, string $categoria): string => "{$categoria}: {$quantidade}")
                        ->implode(', ');

                    $this->components->info("[{$clinica->name}] {$resumo}");
                } else {
                    GerarNotificacoesDaClinica::dispatch($clinica->id);

                    $this->components->info("[{$clinica->name}] varredura enfileirada.");
                }
            } catch (Throwable $exception) {
                $falhas[] = $clinica->name;

                $this->components->error("[{$clinica->name}] {$exception->getMessage()}");
            }
        }

        return $falhas === [] ? self::SUCCESS : self::FAILURE;
    }
}
