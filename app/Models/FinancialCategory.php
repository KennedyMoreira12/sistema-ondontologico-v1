<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria de apoio do financeiro (receita ou despesa).
 *
 * É uma tabela, e não um enum no código, porque a cliente precisa renomear e
 * inativar categorias sem depender de deploy. Os lançamentos apontam para o
 * id; renomear a categoria não reescreve o histórico.
 */
#[Fillable(['nome', 'tipo', 'is_active'])]
class FinancialCategory extends TenantModel
{
    protected $table = 'financeiro_categorias';

    public const RECEITA = 'receita';

    public const DESPESA = 'despesa';

    public const TIPOS = [self::RECEITA, self::DESPESA];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function contasPayable(): HasMany
    {
        return $this->hasMany(AccountsPayable::class, 'financeiro_categoria_id');
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDoTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }
}
