<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'razao_social', 'documento', 'email', 'phone', 'whatsapp', 'cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'logo_path', 'is_active'])]
class Clinica extends \Illuminate\Database\Eloquent\Model
{
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function profissionais(): HasMany
    {
        return $this->hasMany(Professional::class);
    }

    public function salas(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}