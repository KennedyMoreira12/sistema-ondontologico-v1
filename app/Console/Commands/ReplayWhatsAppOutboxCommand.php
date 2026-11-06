<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Models\WhatsAppOutboxMessage;
use App\Services\Tenant\TenantRunner;
use App\Services\WhatsApp\Outbox;
use Illuminate\Console\Command;

/**
 * Tira uma mensagem da DLQ e tenta de novo.
 *
 * É o caminho manual para os casos que a classificação automática recusa:
 * entrega incerta por timeout (pode ter saído) e recusa por credencial
 * corrigida depois. A linha é identificada por clínica + id porque o id da
 * outbox é local ao banco da clínica.
 *
 * `--attempts=0` reinicia a contagem. Isso é decisão de quem opera: reenviar
 * uma entrega incerta pode duplicar a mensagem no WhatsApp do paciente.
 */
class ReplayWhatsAppOutboxCommand extends Command
{
    protected $signature = 'whatsapp:outbox:replay
        {clinic : ID da clínica}
        {outbox : ID da linha na outbox}
        {--attempts=0 : Em que contagem de tentativas recomeçar}';

    protected $description = 'Reprocessa uma mensagem da DLQ da outbox do WhatsApp';

    public function handle(TenantRunner $runner, Outbox $outbox): int
    {
        $clinicId = (int) $this->argument('clinic');
        $outboxId = (int) $this->argument('outbox');

        $resultado = $runner->run($clinicId, function (Clinic $clinica) use ($outbox, $outboxId): ?string {
            $registro = WhatsAppOutboxMessage::query()->find($outboxId);

            if ($registro === null) {
                return "A linha {$outboxId} não existe na outbox de {$clinica->name}.";
            }

            $registro = $outbox->replay($registro, (int) $this->option('attempts'));

            $outbox->dispatch($clinica, $registro);

            return null;
        });

        if ($resultado !== null) {
            $this->components->error($resultado);

            return self::FAILURE;
        }

        $this->components->info("Mensagem {$outboxId} recolocada na fila.");

        return self::SUCCESS;
    }
}
