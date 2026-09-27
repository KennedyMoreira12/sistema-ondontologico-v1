<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['budget_id', 'procedure_id', 'dente', 'descricao', 'quantidade', 'valor_unitario', 'desconto', 'valor_total'])]
class BudgetItem extends TenantModel
{
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }
}
