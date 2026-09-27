<?php

namespace App\Models\Central;

use App\Models\Role;
use App\Models\UserRole;
use App\Services\Tenant\TenantManager;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * Usuário de uma clínica. Vive no banco CENTRAL porque o login acontece antes
 * de existir um tenant — é a partir dele que o sistema descobre qual clínica e
 * qual banco usar.
 *
 * Papel e permissão NÃO vivem aqui: cada clínica tem os seus, no banco dela,
 * via `user_roles` (App\Models\Tenant\UserRole).
 */
#[Fillable(['clinic_id', 'name', 'email', 'password', 'cpf', 'phone', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends CentralAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function tenantConnection(): BelongsTo
    {
        return $this->belongsTo(TenantConnection::class, 'clinic_id', 'clinic_id');
    }

    /**
     * Papéis são POR CLÍNICA, então esta relação só é válida depois que o
     * TenantDatabaseManager conectou no banco da clínica. Chamar antes disso
     * consulta a tabela `user_roles` do banco errado.
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function primaryRole(): ?Role
    {
        return $this->roles()->orderBy('id')->first();
    }

    public function isAdmin(): bool
    {
        return $this->roles->contains(fn (Role $role): bool => $role->name === 'admin');
    }

    public function hasRole(string $name): bool
    {
        return $this->roles->contains(fn (Role $role): bool => $role->name === $name);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles
            ->flatMap(fn (Role $role): array => $role->permissions->pluck('name')->all())
            ->contains($permission);
    }

    public function assignRole(Role $role): void
    {
        $this->userRoles()->firstOrCreate(['role_id' => $role->id]);
    }

    public function syncRoles(array $roleIds): void
    {
        $this->userRoles()->delete();
        $this->userRoles()->createMany(
            collect($roleIds)->map(fn (int $roleId): array => ['role_id' => $roleId])->all(),
        );
    }

    /**
     * A clínica à qual este usuário pertence, resolvida pelo tenant ativo.
     */
    public function currentClinic(): ?Clinic
    {
        return app(TenantManager::class)->current();
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->clinic?->isAccessible() === true;
    }

    public function scopeOfClinic(Builder $query, Clinic $clinic): Builder
    {
        return $query->where('clinic_id', $clinic->id);
    }
}
