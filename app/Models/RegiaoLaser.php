<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Região corporal atendida pela depilação a laser.
 *
 * O catálogo é da clínica: cada banco de tenant tem as suas regiões. A região
 * não pode ser apagada quando já tem pacote ou aplicação vinculados — o
 * histórico aponta para ela.
 */
#[Fillable(['nome', 'descricao', 'ordem', 'is_active'])]
class RegiaoLaser extends TenantModel
{
    protected $table = 'laser_regioes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function pacotes(): HasMany
    {
        return $this->hasMany(PacoteLaser::class, 'regiao_id');
    }

    public function aplicacoes(): HasMany
    {
        return $this->hasMany(AplicacaoLaser::class, 'regiao_id');
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
