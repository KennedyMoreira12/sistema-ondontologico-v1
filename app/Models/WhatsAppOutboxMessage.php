<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Uma mensagem que a clínica decidiu enviar, esperando (ou já tendo passado
 * por) a chamada à Evolution API.
 *
 * A linha nasce ANTES do envio e é a fonte da verdade do que ainda falta sair.
 * Sem ela, uma queda entre a decisão de responder e a chamada HTTP deixava a
 * resposta apenas na memória do processo — e o paciente simplesmente não
 * recebia, sem ninguém saber.
 *
 * `message_key` é a chave de idempotência da outbox: um reenvio do n8n com a
 * mesma `idempotency_key` reencontra a linha em vez de criar outra. `status`
 * separa "ainda vai sair" (`pending`), "um worker pegou" (`processing`),
 * "a Evolution aceitou" (`sent`) e "não sai mais sozinha" (`dead`).
 *
 * `sent` NÃO quer dizer entregue: a Evolution só confirma que aceitou o envio.
 * A entrega e a leitura chegam depois pelo webhook `MESSAGES_UPDATE` e moram em
 * `whatsapp_messages.delivery_status`.
 */
#[Fillable([
    'message_key', 'remote_jid', 'telefone', 'kind', 'body',
    'media_url', 'media_type', 'mime_type', 'file_name', 'caption',
    'payload', 'source', 'status', 'attempts', 'max_attempts',
    'available_at', 'locked_at', 'sent_at',
    'provider_message_id', 'last_error', 'last_error_code', 'correlation_id',
])]
class WhatsAppOutboxMessage extends TenantModel
{
    /**
     * Fixo pelo mesmo motivo de `WhatsAppMessage`: o pluralizador do Laravel
     * separaria "App" de "WhatsApp" e procuraria `whats_app_outbox_messages`.
     */
    protected $table = 'whatsapp_outbox';

    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const SENT = 'sent';

    public const DEAD = 'dead';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Linhas que podem ser reivindicadas por um worker agora.
     *
     * `available_at` nulo é o caso das linhas criadas antes desta coluna ganhar
     * valor; tratá-las como disponíveis evita que fiquem paradas para sempre.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('status', self::PENDING)
            ->where(function (Builder $query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            });
    }

    /**
     * Linhas presas em `processing` além do limite: o worker morreu no meio do
     * envio e ninguém vai terminar o trabalho dele.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeStale(Builder $query, int $minutes): Builder
    {
        return $query
            ->where('status', self::PROCESSING)
            ->whereNotNull('locked_at')
            ->where('locked_at', '<', now()->subMinutes($minutes));
    }

    public function telefoneDoJid(): ?string
    {
        if ($this->remote_jid === null) {
            return null;
        }

        $telefone = preg_replace('/\D/', '', $this->remote_jid);

        return ($telefone === null || $telefone === '') ? null : $telefone;
    }
}
