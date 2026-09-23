<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'documento', 'email', 'phone', 'contato', 'endereco', 'is_active'])]
class Supplier extends \Illuminate\Database\Eloquent\Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}