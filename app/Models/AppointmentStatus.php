<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'cor'])]
class AppointmentStatus extends \Illuminate\Database\Eloquent\Model
{
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}