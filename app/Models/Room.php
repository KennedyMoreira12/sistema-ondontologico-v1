<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clinica_id', 'nome', 'is_active'])]
class Room extends \Illuminate\Database\Eloquent\Model
{
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}