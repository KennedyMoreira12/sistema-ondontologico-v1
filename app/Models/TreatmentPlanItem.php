<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['treatment_plan_id', 'procedure_id', 'dente', 'quantidade', 'valor_unitario', 'desconto', 'valor_total', 'observacao', 'status'])]
class TreatmentPlanItem extends \Illuminate\Database\Eloquent\Model
{
    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }
}