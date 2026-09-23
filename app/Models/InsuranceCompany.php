<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'cnpj', 'email', 'phone', 'responsavel', 'is_active'])]
class InsuranceCompany extends \Illuminate\Database\Eloquent\Model
{
    public function plans(): HasMany
    {
        return $this->hasMany(InsurancePlan::class);
    }
}