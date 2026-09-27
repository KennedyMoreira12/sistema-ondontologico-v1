<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'is_active'])]
class PaymentMethod extends TenantModel
{
    public function pagamentos(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
