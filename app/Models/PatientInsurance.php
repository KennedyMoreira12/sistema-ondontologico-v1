<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['paciente_id', 'insurance_plan_id', 'numero_carteirinha', 'validade', 'is_active'])]
class PatientInsurance extends \Illuminate\Database\Eloquent\Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'validade' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class, 'insurance_plan_id');
    }
}