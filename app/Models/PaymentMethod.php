<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'is_active'])]
class PaymentMethod extends \Illuminate\Database\Eloquent\Model
{
    public function pagamentos(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}