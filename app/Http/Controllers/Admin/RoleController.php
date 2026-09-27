<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin.view');

        // `userRoles`, e não `users`: a tabela `users` é da plataforma e `roles`
        // é da clínica, então um `belongsTo(User::class)` aqui viraria um join
        // entre dois bancos diferentes. O que existe no banco da clínica é o
        // pivot `user_roles`, e é ele que diz quantos usuários têm o papel.
        $roles = Role::withCount('permissions', 'userRoles')->orderBy('name')->get();

        return view('admin.perfis.index', compact('roles'));
    }

    public function edit(Role $role): View
    {
        $this->authorize('admin.update');

        $permissions = Permission::orderBy('module')->orderBy('action')->get()->groupBy('module');

        return view('admin.perfis.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('admin.update');

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:tenant.permissions,id'],
        ]);

        $role->update(['label' => $validated['label']]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        AuditLogger::log('atualizar_permissoes', 'roles', $role->id, depois: ['permissions' => $validated['permissions'] ?? []]);

        return redirect()->route('admin.perfis.edit', $role)->with('success', 'Perfil atualizado com sucesso.');
    }
}
