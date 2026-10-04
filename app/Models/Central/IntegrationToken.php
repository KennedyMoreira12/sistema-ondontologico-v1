<?php

namespace App\Models\Central;

use App\Support\IntegrationAbilities;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
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
        /**
         * Token sem abilities não faz nada.
         *
         * O contrário — `null` valendo para tudo — foi o que يجعل a coluna
         * inofensiva desde que ela existe: o painel criava token sem gravar
         * `abilities`, e um token que deveria ler a agenda podia criar e cancelar
         * consulta. Ausência de permissão não pode significar permissão total.
         * Liberação total é o wildcard, e ele é escolhido por alguém.
         */
        if ($this->abilities === null || $this->abilities === []) {
            return false;
        }

        return in_array(IntegrationAbilities::WILDCARD, $this->abilities, true)
            || in_array($ability, $this->abilities, true);
    }

    /**
     * Extrai o token em claro da requisição: `Authorization: Bearer <token>` e
     * os headers legados que o n8n já enviava.
     *
     * Vive no model para que o middleware de autenticação e o rate limiter leiam
     * o mesmo header com o mesmo código — duas extrações divergentes dão limite
     * por IP num caminho e por token no outro.
     */
    public static function extractFromRequest(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if ($bearer !== null && $bearer !== '') {
            return $bearer;
        }

        foreach (['X-WhatsApp-Token', 'X-Integration-Token'] as $header) {
            $value = trim((string) $request->header($header, ''));

            if ($value === '') {
                continue;
            }

            return str_starts_with($value, 'WhatsApp ') ? substr($value, 9) : $value;
        }

        return null;
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
