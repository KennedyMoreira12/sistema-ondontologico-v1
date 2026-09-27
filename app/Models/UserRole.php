<?php

namespace App\Models;

use App\Models\Central\User;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligação entre um usuário do banco central e um papel daquele banco de
 * clínica. `user_id` não tem FK porque aponta para outra base.
 *
 * O model é de clínica (TenantModel), não central: a tabela `user_roles`
 * fica no mesmo banco dos papéis que ela referencia.
 */
#[Fillable(['user_id', 'role_id'])]
class UserRole extends TenantModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
