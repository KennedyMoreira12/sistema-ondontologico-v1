<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_category_id', 'supplier_id', 'nome', 'codigo', 'unidade', 'estoque', 'estoque_minimo', 'custo', 'preco', 'validade', 'lote', 'controlar_estoque', 'is_active'])]
class Product extends \Illuminate\Database\Eloquent\Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'validade' => 'date',
            'controlar_estoque' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getEstoqueBaixoAttribute(): bool
    {
        return $this->controlar_estoque && $this->estoque <= $this->estoque_minimo;
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}