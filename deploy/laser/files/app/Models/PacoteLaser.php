<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pacote-modelo de sessões de laser para uma região.
 *
 * É catálogo, não pacote contratado: o valor e a validade servem de referência
 * para a venda, e registrar uma aplicação NÃO gera cobrança. O histórico de
 * aplicações é que mostra quantas sessões já foram feitas.
 */
#[Fillable([
    'regiao_id', 'nome', 'total_sessoes', 'valor', 'validade_dias',
    'descricao', 'is_active',
])]
class PacoteLaser extends TenantModel
{
    protected $table = 'laser_pacotes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_sessoes' => 'integer',
            'validade_dias' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function regiao(): BelongsTo
    {
        return $this->belongsTo(RegiaoLaser::class, 'regiao_id');
    }

    public function aplicacoes(): HasMany
    {
        return $this->hasMany(AplicacaoLaser::class, 'pacote_id');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
