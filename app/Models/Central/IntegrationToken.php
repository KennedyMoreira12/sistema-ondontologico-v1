<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token de uma integração (n8n chamando a API interna, webhooks da Meta).
 *
 * O valor em claro existe uma única vez, na criação. O banco guarda apenas
 * SHA-256, então a comparação é por hash e um token vazado não permite
 * recuperar o original. Cada clínica tem o seu — um token único global daria
 * acesso a todas as clínicas.
 */
#[Fillable(['clinic_id', 'name', 'token_hash', 'abilities', 'last_used_at', 'last_used_ip', 'revoked_at'])]
class IntegrationToken extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public static function generateToken(): string
    {
        return Str::random(40);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function hasAbility(string $ability): bool
    {
        if ($this->abilities === null || $this->abilities === []) {
            return true;
        }

        return in_array('*', $this->abilities, true) || in_array($ability, $this->abilities, true);
    }

    public function markUsed(?string $ip = null): void
    {
        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->save();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function maskedHint(): string
    {
        return substr($this->token_hash, 0, 8).'…';
    }
}
