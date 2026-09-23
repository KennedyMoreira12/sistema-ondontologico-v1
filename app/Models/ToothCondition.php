<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'codigo', 'cor', 'icone', 'is_default'])]
class ToothCondition extends \Illuminate\Database\Eloquent\Model
{
    public function dentes(): HasMany
    {
        return $this->hasMany(OdontogramTooth::class);
    }
}