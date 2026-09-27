<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Papel dentro de UMA clínica. A tabela `roles` mora no banco dessa clínica, o
 * que significa que "admin" na Clínica A e "admin" na Clínica B são linhas
 * diferentes, com permissões que podem divergir.
 */
#[Fillable(['name', 'label'])]
class Role extends TenantModel
{
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function userRoles(): BelongsToMany
    {
        return $this->belongsToMany(UserRole::class, 'role_id', 'id', 'user_id');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }

    public function isAdministrator(): bool
    {
        return $this->name === 'admin';
    }
}
