<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['insurance_company_id', 'nome', 'registro', 'tipo', 'is_active'])]
class InsurancePlan extends \Illuminate\Database\Eloquent\Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'insurance_company_id');
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(PatientInsurance::class);
    }
}