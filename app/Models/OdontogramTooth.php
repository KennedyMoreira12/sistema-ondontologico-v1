<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['odontogram_id', 'dente', 'status', 'observacao', 'profissional_id'])]
class OdontogramTooth extends TenantModel
{
    public function odontogram(): BelongsTo
    {
        return $this->belongsTo(Odontogram::class);
    }

    public function condicoes(): HasMany
    {
        return $this->hasMany(OdontogramToothCondition::class, 'odontogram_tooth_id');
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
