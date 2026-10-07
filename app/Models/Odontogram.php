<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['paciente_id', 'titulo', 'status', 'denticao', 'notacao', 'notas'])]
class Odontogram extends TenantModel
{
    protected $attributes = [
        'titulo' => 'Odontograma principal',
        'status' => 'ativo',
        'denticao' => 'permanente',
        'notacao' => 'FDI',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function dentes(): HasMany
    {
        return $this->hasMany(OdontogramTooth::class);
    }
}
