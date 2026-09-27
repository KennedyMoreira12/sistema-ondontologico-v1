<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'cor'])]
class AppointmentStatus extends TenantModel
{
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
