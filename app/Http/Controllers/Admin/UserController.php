<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\Tenant\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Usuários DA CLÍNICA em atendimento.
 *
 * A clínica nunca vem do formulário: é o tenant ativo, resolvido a partir de
 * `users.clinic_id` no login. Isso garante que um admin da Clínica A não
 * consiga, por adulterar a URL, criar ou editar um usuário da Clínica B.
 */
class UserController extends Controller
{
    public function __construct(protected TenantManager $tenants) {}

    public function index(): View
    {
        $this->authorize('admin.view');

        $users = $this->clinic()
            ->users()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.usuarios.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('admin.create');

        return view('admin.usuarios.create', [
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->authorize('admin.create');

        $validated = $request->safe()->except('role_ids');

        $user = $this->clinic()->users()->create($validated);
        $user->syncRoles($request->validated('role_ids'));

        AuditLogger::log('criar', 'users', $user->id, depois: $user->only(['name', 'email']));

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(string $usuario): View
    {
        $this->authorize('admin.update');

        return view('admin.usuarios.edit', [
            'user' => $this->findInClinic($usuario),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(UserUpdateRequest $request, string $usuario): RedirectResponse
    {
        $this->authorize('admin.update');

        $user = $this->findInClinic($usuario);
        $antes = $user->only(['name', 'email', 'cpf', 'phone', 'is_active']);

        $user->update($request->safe()->except('role_ids'));
        $user->syncRoles($request->validated('role_ids'));

        AuditLogger::log(
            'atualizar',
            'users',
            $user->id,
            antes: $antes,
            depois: $user->fresh()->load('roles')->only(['name', 'email', 'cpf', 'phone', 'is_active']),
        );

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(string $usuario): RedirectResponse
    {
        $this->authorize('admin.delete');

        $user = $this->findInClinic($usuario);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode excluir o próprio usuário.');
        }

        AuditLogger::log('excluir', 'users', $user->id, antes: $user->only(['name', 'email']));

        // Os papéis vivem no banco da clínica; sem remover junto sobrariam
        // linhas órfãs em user_roles apontando para um usuário inexistente.
        $user->userRoles()->delete();
        $user->delete();

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuário excluído.');
    }

    protected function clinic(): Clinic
    {
        $clinic = $this->tenants->current();

        abort_if($clinic === null, 403, 'Nenhuma clínica resolvida para esta requisição.');

        return $clinic;
    }

    /**
     * Busca o usuário sempre dentro da clínica em atendimento. Um id de outra
     * clínica devolve 404, e não 403, para não confirmar que aquele usuário
     * existe em outro lugar.
     */
    protected function findInClinic(string $id): User
    {
        return $this->clinic()->users()->whereKey($id)->firstOrFail();
    }
}
