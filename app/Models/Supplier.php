<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'documento', 'email', 'phone', 'contato', 'endereco', 'is_active'])]
class Supplier extends TenantModel
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
