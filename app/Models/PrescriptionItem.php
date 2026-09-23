<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['prescription_id', 'medicamento', 'dose', 'frequencia', 'duracao', 'observacao'])]
class PrescriptionItem extends \Illuminate\Database\Eloquent\Model
{
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}