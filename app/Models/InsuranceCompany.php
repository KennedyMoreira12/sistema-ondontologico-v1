<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'cnpj', 'email', 'phone', 'responsavel', 'is_active'])]
class InsuranceCompany extends TenantModel
{
    public function plans(): HasMany
    {
        return $this->hasMany(InsurancePlan::class);
    }
}
