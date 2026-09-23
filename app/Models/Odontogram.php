<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['paciente_id', 'titulo', 'status'])]
class Odontogram extends \Illuminate\Database\Eloquent\Model
{
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function dentes(): HasMany
    {
        return $this->hasMany(OdontogramTooth::class);
    }
}