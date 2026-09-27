<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Specialty extends TenantModel
{
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class);
    }
}
