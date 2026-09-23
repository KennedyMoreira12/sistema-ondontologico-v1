<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Specialty extends \Illuminate\Database\Eloquent\Model
{
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class);
    }
}