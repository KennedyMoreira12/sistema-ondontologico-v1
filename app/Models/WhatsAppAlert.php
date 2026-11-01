<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sinal de que uma conversa precisa de gente.
 *
 * O bot pede humano, o expediente fecha com paciente na linha, ou a recepção
 * pausa o atendimento. Em todos os casos o paciente fica esperando alguém — e
 * sem um registro fica esperando para sempre, porque nada no sistema mostrava
 * essa fila.
 *
 * Enquanto `atendido_em` for nulo, o alerta está aberto. É a fila da recepção,
 * e é o que a Fase 4 transforma em tela.
 */
#[Fillable(['remote_jid', 'tipo', 'motivo', 'atendido_em', 'atendido_por'])]
class WhatsAppAlert extends TenantModel
{
    protected $table = 'whatsapp_alerts';

    public const HANDOFF = 'handoff';

    public const FORA_EXPEDIENTE = 'fora_expediente';

    public const PAUSA = 'pausa';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'atendido_em' => 'datetime',
            'atendido_por' => 'integer',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAbertos(Builder $query): Builder
    {
        return $query->whereNull('atendido_em');
    }

    public function estaAberto(): bool
    {
        return $this->atendido_em === null;
    }

    public function marcarAtendido(?int $userId = null): void
    {
        if ($this->atendido_em !== null) {
            return;
        }

        $this->atendido_em = now();
        $this->atendido_por = $userId;
        $this->save();
    }
}
