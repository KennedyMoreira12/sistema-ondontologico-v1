<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ProductCategory extends TenantModel
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
