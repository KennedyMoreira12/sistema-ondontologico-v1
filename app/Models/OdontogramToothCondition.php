<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['odontogram_tooth_id', 'tooth_condition_id', 'superficie', 'camada', 'descricao', 'profissional_id'])]
class OdontogramToothCondition extends TenantModel
{
    public function dente(): BelongsTo
    {
        return $this->belongsTo(OdontogramTooth::class, 'odontogram_tooth_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(ToothCondition::class, 'tooth_condition_id');
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
