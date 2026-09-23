<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['budget_id', 'procedure_id', 'dente', 'descricao', 'quantidade', 'valor_unitario', 'desconto', 'valor_total'])]
class BudgetItem extends \Illuminate\Database\Eloquent\Model
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