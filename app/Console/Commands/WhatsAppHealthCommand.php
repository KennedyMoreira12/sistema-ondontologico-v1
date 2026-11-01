<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Services\Tenant\TenantRunner;
use App\Services\WhatsApp\WhatsAppHealth;
use Illuminate\Console\Command;
use Throwable;

/**
 * Retrato da operação do WhatsApp por clínica, sem enviar mensagem.
 *
 * É o comando que responde "por que a clínica X não está respondendo?":
 * integração ausente, WhatsApp desconectado, fila parada, DLQ acumulando.
 *
 * A consulta à Evolution é opt-in (`--check-evolution`): um comando de
 * diagnóstico não pode depender de um terceiro fora do ar para terminar.
 */
class WhatsAppHealthCommand extends Command
{
    protected $signature = 'whatsapp:health
        {--clinic= : ID de uma clínica específica (padrão: todas)}
        {--check-evolution : Consultar o estado da conexão na Evolution}
        {--json : Imprimir o resultado em JSON}';

    protected $description = 'Mostra a saúde da integração e da outbox do WhatsApp por clínica';

    public function handle(TenantRunner $runner, WhatsAppHealth $health): int
    {
        $clinicas = Clinic::query()
            ->where('is_active', true)
            ->whereHas('tenantConnection')
            ->when($this->option('clinic'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($clinicas->isEmpty()) {
            $this->components->warn('Nenhuma clínica ativa com banco configurado.');

            return self::SUCCESS;
        }

        $verificar = (bool) $this->option('check-evolution');
        $resultado = [];
        $falhas = [];

        foreach ($clinicas as $clinica) {
            try {
                $resultado[] = $runner->run(
                    $clinica,
                    fn (Clinic $clinic): array => $health->snapshot($clinic, $verificar),
                );
            } catch (Throwable $exception) {
                $falhas[] = $clinica->name;

                $resultado[] = [
                    'clinic_id' => $clinica->id,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]');

            return $falhas === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Clínica', 'Integração', 'Conexão', 'Pendentes', 'Processando', 'DLQ', 'Órfãs'],
            array_map(function (array $linha): array {
                $outbox = $linha['outbox'] ?? [];

                return [
                    $linha['clinic_id'] ?? '—',
                    isset($linha['integration_ready'])
                        ? ($linha['integration_ready'] ? 'pronta' : 'faltando')
                        : 'erro',
                    $linha['connection']['state']
                        ?? $linha['connection']['error']
                        ?? ($this->option('check-evolution') ? '—' : 'não verificada'),
                    $outbox['pending'] ?? '—',
                    $outbox['processing'] ?? '—',
                    $outbox['dead'] ?? '—',
                    $outbox['stale_processing'] ?? '—',
                ];
            }, $resultado),
        );

        $comMortas = array_filter($resultado, fn (array $linha): bool => ($linha['outbox']['dead'] ?? 0) > 0);

        if ($comMortas !== []) {
            $this->components->warn('Há mensagens na DLQ. Use whatsapp:outbox:replay {clinic} {outbox} para revisar.');
        }

        return $falhas === [] ? self::SUCCESS : self::FAILURE;
    }
}
