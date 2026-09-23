<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nome', 'categoria', 'duracao_minutos', 'valor_padrao', 'descricao', 'is_active'])]
class Procedure extends \Illuminate\Database\Eloquent\Model
{
    public function planosItens(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class);
    }
}