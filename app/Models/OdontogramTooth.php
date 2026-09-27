<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['odontogram_id', 'tooth_condition_id', 'dente', 'condicao', 'observacao', 'profissional_id'])]
class OdontogramTooth extends TenantModel
{
    public function odontogram(): BelongsTo
    {
        return $this->belongsTo(Odontogram::class);
    }

    public function toothCondition(): BelongsTo
    {
        return $this->belongsTo(ToothCondition::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
