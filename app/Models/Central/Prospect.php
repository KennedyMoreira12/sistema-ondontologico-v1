<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Um contato/oportunidade da prospecção comercial da LJ Automação.
 *
 * Vive no banco CENTRAL, e não no banco de uma clínica: é a base comercial da
 * própria plataforma e não pode ser confundida com pacientes ou clientes de
 * tenant nenhum.
 */
#[Fillable([
    'import_id', 'name', 'name_normalized', 'type', 'category', 'specialty',
    'phone_original', 'phone_alt', 'phone_normalized', 'phone_status',
    'city', 'state', 'address', 'priority', 'source', 'source_ref', 'status',
    'last_contact_at', 'next_follow_up_at', 'proximo', 'notes', 'disinterest_reason',
    'opted_out', 'opted_out_at', 'validated_at', 'converted_clinic_id',
    'bot_pause', 'bot_paused_at',
])]
class Prospect extends CentralModel
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_contact_at' => 'datetime',
            'next_follow_up_at' => 'date',
            'proximo' => 'string',
            'opted_out' => 'boolean',
            'opted_out_at' => 'datetime',
            'validated_at' => 'datetime',
            'bot_pause' => 'boolean',
            'bot_paused_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ProspectImport::class, 'import_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ProspectInteraction::class)->orderByDesc('interacted_at')->orderByDesc('id');
    }

    /**
     * Todas as procedências (linhas dos PDFs) que apontam para este contato.
     */
    public function origins(): HasMany
    {
        return $this->hasMany(ProspectOrigin::class)->orderBy('source')->orderBy('source_ref');
    }

    public function convertedClinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'converted_clinic_id');
    }

    public function statusLabel(): string
    {
        return (string) config("prospeccao.statuses.{$this->status}.nome", $this->status);
    }

    public function priorityLabel(): string
    {
        return (string) config("prospeccao.priorities.{$this->priority}", $this->priority);
    }

    public function phoneStatusLabel(): string
    {
        return (string) config("prospeccao.phone_statuses.{$this->phone_status}", $this->phone_status);
    }

    public function proximoLabel(): string
    {
        return (string) config("prospeccao.statuses.{$this->proximo}", $this->proximo ?? '');
    }

    public function botStatus(): string
    {
        return $this->bot_pause ? 'Pausado' : 'Ativo';
    }

    public function estaPausadoBot(): bool
    {
        return $this->bot_pause === true;
    }

    public function estaBloqueado(): bool
    {
        return $this->opted_out === true;
    }

    /**
     * Pode receber abordagem comercial: telefone válido, não optou por bloqueio,
     * e bot de prospecção não está pausado para este contato.
     */
    public function podeReceberComercial(): bool
    {
        return ! $this->estaBloqueado()
            && $this->hasContactablePhone()
            && ! $this->estaPausadoBot();
    }

    /**
     * Registra uma interação/comercial no histórico.
     *
     * @param  string  $acao  'preparar' | 'aprovar_enviar' | 'enviado' | 'erro' | 'pausado' | 'retomado'
     * @param  string  $resultado  resultado do envio ou motivo do bloqueio
     * @param  string  $operador  nome do operador (user name)
     * @param  string  $observacao  observação livre
     * @return \App\Models\Central\ProspectInteraction
     */
    public function registrarInteracaoComercial(string $acao, ?string $resultado = null, ?string $observacao = null): \App\Models\Central\ProspectInteraction
    {
        return $this->interactions()->create([
            'channel' => 'whatsapp_comercial',
            'status_before' => $this->status,
            'status_after' => $this->status,
            'result' => $resultado,
            'notes' => $observacao,
            'interacted_at' => now(),
        ]);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['cliente', 'sem_interesse'], true);
    }

    /**
     * Telefone bom o bastante para abrir uma conversa: número normalizado e não
     * marcado como não confirmado/inválido.
     */
    public function hasContactablePhone(): bool
    {
        return $this->phone_normalized !== null
            && in_array($this->phone_status, ['valid', 'incomplete'], true);
    }

    /**
     * Link `wa.me` para o operador revisar e enviar manualmente. Devolve null se
     * não há número confiável ou se o contato pediu para não receber abordagens.
     */
    public function whatsappUrl(?string $message = null): ?string
    {
        if ($this->opted_out || ! $this->hasContactablePhone()) {
            return null;
        }

        $url = 'https://wa.me/'.$this->phone_normalized;

        if ($message !== null && $message !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNotIn('status', ['cliente', 'sem_interesse']);
    }

    #[Scope]
    protected function contactable(Builder $query): void
    {
        $query->where('opted_out', false)
            ->whereNotNull('phone_normalized')
            ->whereIn('phone_status', ['valid', 'incomplete']);
    }

    #[Scope]
    protected function pendingFollowUp(Builder $query): void
    {
        $query->whereNotNull('next_follow_up_at')
            ->whereDate('next_follow_up_at', '<=', now()->toDateString())
            ->whereNotIn('status', ['cliente', 'sem_interesse']);
    }
}
