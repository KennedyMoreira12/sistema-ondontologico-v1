<?php

namespace App\Models\Central;

use App\Models\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registro central de uma clínica. A clínica É o tenant: ela aponta para o
 * banco onde vivem os dados clínicos dela.
 */
#[Fillable([
    'name', 'legal_name', 'document', 'email', 'phone', 'whatsapp',
    'evolution_instance', 'evolution_token',
    'logo_path', 'primary_color', 'secondary_color',
    'status', 'vertical', 'timezone', 'is_active', 'trial_ends_at',
    'suspended_at', 'suspension_reason',
])]
class Clinic extends CentralModel
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',

            /**
             * O token da instância é usado em claro na chamada, então não pode
             * ser hash como o do `integration_tokens` — mas também não fica em
             * texto puro na tabela. O cast `encrypted` cifra com a APP_KEY, o
             * que mantém o dump do banco central inútil sozinho.
             */
            'evolution_token' => 'encrypted',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tenantConnection(): HasOne
    {
        return $this->hasOne(TenantConnection::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function integrationTokens(): HasMany
    {
        return $this->hasMany(IntegrationToken::class);
    }

    public function usageMetrics(): HasMany
    {
        return $this->hasMany(UsageMetric::class);
    }

    /**
     * Assinatura que LIBERA o acesso: trialing ou active, e não expirada.
     *
     * Os estados vão no segundo argumento de `ofMany`, e não como `whereIn`
     * antes dele: assim o filtro entra na subquery que escolhe a mais recente,
     * em vez de ser aplicado só depois da escolha.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['created_at' => 'max'],
            fn (Builder $query) => $query->whereIn('status', config('tenancy.accessible_subscription_states')),
        );
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * O plano em vigor, vindo da assinatura ativa — nunca de uma coluna
     * `plan_id` na clínica, senão uma assinatura vencida continuaria liberando.
     */
    public function currentPlan(): ?Plan
    {
        $subscription = $this->activeSubscription ?? $this->latestSubscription;

        return $subscription?->plan;
    }

    /**
     * Decide pelo status da PRÓPRIA clínica (`clinics.status`), comparado com
     * `tenancy.accessible_clinic_states`.
     */
    public function isAccessible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return in_array($this->status, config('tenancy.accessible_clinic_states'), true);
    }

    public function isOnGracePeriod(): bool
    {
        if ($this->status !== 'past_due') {
            return false;
        }

        $subscription = $this->latestSubscription;

        return $subscription?->grace_ends_at === null
            || $subscription->grace_ends_at->isFuture();
    }

    public function hasFeature(string $feature): bool
    {
        return $this->currentPlan?->hasFeature($feature) ?? false;
    }

    public function featureLimit(string $feature, ?string $key = null): mixed
    {
        $limits = $this->currentPlan?->featureLimits($feature) ?? [];

        return $key === null ? $limits : ($limits[$key] ?? null);
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial'
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereIn('status', config('tenancy.accessible_clinic_states'));
    }

    /**
     * Clínicas que já têm a integração Evolution completa.
     *
     * Instância sem token não entra: o Laravel chamaria `/message/sendText` sem
     * `apikey` válido e receberia 401 em toda resposta. Filtrar na consulta
     * economiza o decrypt de token de clínica que não manda nada.
     */
    #[Scope]
    protected function withEvolution(Builder $query): void
    {
        $query->whereNotNull('evolution_instance')
            ->whereNotNull('evolution_token');
    }

    /**
     * A clínica está pronta para a Evolution falar com ela?
     *
     * `isAccessible()` entra porque instance e token cadastrados não dizem se a
     * assinatura ainda vale: uma clínica suspensa tem o par completo e não pode
     * agendar consulta pelo WhatsApp.
     */
    public function hasEvolutionIntegration(): bool
    {
        return $this->evolution_instance !== null
            && $this->evolution_token !== null
            && $this->evolution_instance !== ''
            && $this->evolution_token !== '';
    }

    /**
     * Papéis são POR CLÍNICA e vivem no banco dela, sem `clinic_id` para
     * filtrar: o recorte é o próprio banco conectado. Chega até eles pelos
     * usuários da clínica, e só é válido com um tenant ativo.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    /**
     * O administrador da clínica. `hasOne` porque o filtro é por `users.id`
     * (chave da clínica); um `belongsTo` aqui não faria sentido.
     */
    public function administrator(): HasOne
    {
        return $this->hasOne(User::class)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'admin'));
    }
}
