<?php

namespace App\Jobs;

use App\Services\Tenant\TenantRunner;
use App\Services\WhatsApp\Outbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia UMA linha da outbox da clínica dona da mensagem.
 *
 * O job carrega apenas ids — nunca o corpo da mensagem nem a conexão aberta.
 * Um worker atende clínicas diferentes em sequência, e carregar o model
 * serializado com a conexão `tenant` faria a próxima execução escrever no banco
 * da clínica anterior. O `TenantRunner` reconecta no dono e restaura o estado
 * no fim.
 *
 * `$tries = 1`: o retry é da outbox, com backoff e classificação próprios.
 * Deixar a fila repetir também transformaria um timeout ambíguo em mensagem
 * duplicada no celular do paciente.
 */
class SendWhatsAppOutboxMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        public int $clinicId,
        public int $outboxId,
    ) {}

    public function handle(TenantRunner $runner, Outbox $outbox): void
    {
        $runner->run($this->clinicId, function () use ($outbox): void {
            $outbox->process($this->outboxId);
        });
    }

    public function failed(?Throwable $exception): void
    {
        // O processamento normal captura as falhas e grava a DLQ na própria
        // linha. Chegar aqui significa que o job morreu antes disso (timeout do
        // worker, OOM): fica o registro no log para o operador cruzar com a
        // recuperação de órfãos.
        Log::error('Job da outbox do WhatsApp falhou antes de classificar a mensagem.', [
            'clinic_id' => $this->clinicId,
            'outbox_id' => $this->outboxId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
