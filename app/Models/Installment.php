<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['accounts_receivable_id', 'numero', 'vencimento', 'valor', 'valor_pago', 'status'])]
class Installment extends \Illuminate\Database\Eloquent\Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vencimento' => 'date',
        ];
    }

    public function getSaldoAttribute(): float
    {
        return round(($this->valor - $this->valor_pago), 2);
    }

    public function accountsReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountsReceivable::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}