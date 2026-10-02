<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Ações do painel da empresa. Ações feitas dentro de uma clínica ficam no
 * `audit_logs` do banco dela, não aqui.
 */
#[Fillable([
    'admin_user_id', 'clinic_id', 'action', 'entity', 'entity_id',
    'metadata', 'ip', 'user_agent',
])]
class PlatformAuditLog extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function scopeForClinic(Builder $query, Clinic $clinic): Builder
    {
        return $query->where('clinic_id', $clinic->id);
    }

    /**
     * Resumo do `metadata` para a listagem da auditoria. Fica no model porque é
     * a MESMA leitura em toda tela que lista log, e json_encode dentro do Blade
     * não é lugar para política de truncamento.
     */
    public function metadataSummary(int $limit = 120): string
    {
        if ($this->metadata === null || $this->metadata === []) {
            return '—';
        }

        return Str::limit(
            (string) json_encode($this->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $limit,
        );
    }
}
