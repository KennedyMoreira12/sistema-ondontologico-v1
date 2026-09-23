<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\Clinica;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin.view');

        $users = User::with('role')->orderBy('name')->paginate(15);

        return view('admin.usuarios.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('admin.create');

        $roles = Role::orderBy('name')->get();
        $clinicas = Clinica::orderBy('nome')->get();

        return view('admin.usuarios.create', compact('roles', 'clinicas'));
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->authorize('admin.create');

        $user = User::create($request->validated());

        AuditLogger::log('criar', 'users', $user->id, depois: $user->only(['name', 'email', 'role_id']));

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(User $user): View
    {
        $this->authorize('admin.update');

        $roles = Role::orderBy('name')->get();
        $clinicas = Clinica::orderBy('nome')->get();

        return view('admin.usuarios.edit', compact('user', 'roles', 'clinicas'));
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->authorize('admin.update');

        $before = $user->only(['name', 'email', 'role_id', 'is_active']);

        $user->update($request->validated());

        AuditLogger::log('atualizar', 'users', $user->id, antes: $before, depois: $user->fresh()->only(['name', 'email', 'role_id', 'is_active']));

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('admin.delete');

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode excluir o próprio usuário.');
        }

        AuditLogger::log('excluir', 'users', $user->id, antes: $user->only(['name', 'email']));

        $user->delete();

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário excluído.');
    }
}