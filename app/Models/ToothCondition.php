<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'codigo', 'cor', 'icone', 'is_default'])]
class ToothCondition extends TenantModel
{
    public function dentes(): HasMany
    {
        return $this->hasMany(OdontogramTooth::class);
    }
}
