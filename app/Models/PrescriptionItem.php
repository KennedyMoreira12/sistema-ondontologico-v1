<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['prescription_id', 'medicamento', 'dose', 'frequencia', 'duracao', 'observacao'])]
class PrescriptionItem extends TenantModel
{
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
