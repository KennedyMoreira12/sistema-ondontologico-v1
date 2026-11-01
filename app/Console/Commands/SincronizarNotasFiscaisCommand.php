<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Models\NotaFiscal;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\NotaFiscalService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Atualiza o status das notas transmitidas.
 *
 * Provedores como Focus NFe e PlugNotas respondem `processando` e só
 * confirmam depois; sem este comando a tela continuaria mostrando a nota como
 * pendente para sempre. Também serve para descobrir um cancelamento feito
 * direto no painel do provedor.
 *
 * Agenda sugerida (routes/console.php): a cada 30 minutos.
 */
class SincronizarNotasFiscaisCommand extends Command
{
    protected $signature = 'fiscal:sincronizar
        {--clinic= : ID de uma clínica específica (padrão: todas)}
        {--dias=30 : Janela, em dias, das notas autorizadas a consultar}';

    protected $description = 'Consulta o provedor fiscal e atualiza o status das notas fiscais';

    public function handle(TenantDatabaseManager $databases): int
    {
        $clinicas = Clinic::query()
            ->when($this->option('clinic'), fn ($query, $id) => $query->whereKey($id))
            ->whereHas('tenantConnection')
            ->with('tenantConnection')
            ->get();

        if ($clinicas->isEmpty()) {
            $this->components->error('Nenhuma clínica com banco configurado.');

            return self::FAILURE;
        }

        $limite = Carbon::now()->subDays(max(1, (int) $this->option('dias')));

        foreach ($clinicas as $clinica) {
            $this->components->info("Clínica: {$clinica->name}");

            try {
                $databases->connectFor($clinica, allowInactive: true);

                $notas = NotaFiscal::query()
                    ->where(function ($query) use ($limite) {
                        $query->where('status', NotaFiscal::STATUS_PROCESSANDO)
                            ->orWhere(function ($query) use ($limite) {
                                $query->where('status', NotaFiscal::STATUS_AUTORIZADA)
                                    ->where('emitida_em', '>=', $limite);
                            });
                    })
                    ->orderBy('id')
                    ->get();

                if ($notas->isEmpty()) {
                    $this->components->twoColumnDetail('Notas a sincronizar', '0');

                    continue;
                }

                $consultadas = 0;
                $falhas = 0;

                foreach ($notas as $nota) {
                    try {
                        app(NotaFiscalService::class)->consultar($nota);
                        $consultadas++;
                    } catch (FiscalException $e) {
                        $falhas++;
                        $this->components->warn("Nota #{$nota->id}: {$e->getMessage()}");
                    } catch (Throwable $e) {
                        $falhas++;
                        $this->components->warn("Nota #{$nota->id}: erro inesperado — {$e->getMessage()}");
                    }
                }

                $this->components->twoColumnDetail('Notas consultadas', (string) $notas->count());
                $this->components->twoColumnDetail('Consultadas com sucesso', (string) $consultadas);
                $this->components->twoColumnDetail('Falhas', (string) $falhas);
            } catch (Throwable $e) {
                $this->components->error("Falha na clínica [{$clinica->name}]: {$e->getMessage()}");
            } finally {
                $databases->forget();
            }
        }

        return self::SUCCESS;
    }
}
