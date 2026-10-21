<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'remote_jid', 'etapa', 'dados', 'expires_at',
    'bot_status', 'pausado_ate', 'humano_iniciado_em', 'ia_mensagens', 'ia_ultimo_erro',
])]
class WhatsAppConversation extends TenantModel
{
    protected $table = 'whatsapp_conversations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'expires_at' => 'datetime',
            'pausado_ate' => 'datetime',
            'humano_iniciado_em' => 'datetime',
            'ia_mensagens' => 'integer',
        ];
    }

    /**
     * Se o bot pode responder automaticamente agora.
     *
     * `pausado` com prazo vencido volta a responder: a pausa por tempo é o que
     * a recepção usa quando sai para o almoço, e é justamente por ter prazo que
     * não precisa de alguém lembrando de retomá-la manualmente. Pausa sem prazo
     * (`pausado_ate` nulo) fica parada até alguém retomar de propósito.
     */
    public function botAtivo(): bool
    {
        $estado = $this->bot_status ?? 'ativo';

        if (! in_array($estado, config('tenancy.bot_states_ativos', ['ativo']), true)) {
            return false;
        }

        if ($estado === 'pausado' && $this->pausado_ate !== null && $this->pausado_ate->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Marca esta conversa como atendida por um humano e devolve o bot ao silêncio.
     */
    public function assumirPorHumano(): void
    {
        $this->bot_status = 'humano';
        $this->humano_iniciado_em = now();
        $this->pausado_ate = null;
        $this->save();
    }

    /**
     * Devolve a conversa ao bot.
     */
    public function retomarBot(): void
    {
        $this->bot_status = 'ativo';
        $this->humano_iniciado_em = null;
        $this->pausado_ate = null;
        $this->save();
    }
}
