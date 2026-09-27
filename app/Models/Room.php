<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clinica_id', 'nome', 'is_active'])]
class Room extends TenantModel
{
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
