<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clinica_id', 'user_id', 'nome', 'cpf', 'cro', 'tipo', 'comissao', 'is_active'])]
class Professional extends \Illuminate\Database\Eloquent\Model
{
    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}