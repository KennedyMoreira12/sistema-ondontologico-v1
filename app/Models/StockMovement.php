<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'user_id', 'tipo', 'quantidade', 'saldo_anterior', 'saldo_posterior', 'custo', 'motivo'])]
class StockMovement extends \Illuminate\Database\Eloquent\Model
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}