<?php

namespace Tests\Concerns;

use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Permission;
use App\Models\Role;

/**
 * Preparo das clínicas fictícias usadas nos testes de qualquer ramo.
 *
 * Como o sistema é vertical-agnóstico, qualquer clínica de qualquer ramo
 * acessa todos os módulos. Estes testes criam clínicas com valores diferentes
 * de vertical para provar que a vertical não restringe nada.
 */
trait PreparaClinicaFisioterapia
{
    /**
     * Clínica com qualquer ramo/vertical (padrão).
     *
     * @return array{clinic: Clinic, user: ?User, token: string}
     */
    protected function clinicaOdontologica(): array
    {
        return $this->criarClinica('Clínica Qualquer');
    }

    /**
     * Clínica com um ramo/vertical diferente, com um usuário que tem as
     * permissões pedidas.
     *
     * @return array{clinica: array{clinic: Clinic, user: ?User, token: string}, user: User}
     */
    protected function clinicaFisioterapia(string ...$permissoes): array
    {
        $clinica = $this->criarClinica('Clínica Qualquer');
        $clinica['clinic']->update(['vertical' => 'qualquer ramo']);

        $user = $this->usuarioComPermissao($clinica, ...$permissoes);

        return ['clinica' => $clinica, 'user' => $user];
    }

    /**
     * Papel com exatamente as permissões pedidas, no banco da clínica atual.
     *
     * @param  array{clinic: Clinic, user: ?User, token: string}  $clinica
     */
    protected function usuarioComPermissao(array $clinica, string ...$permissoes): User
    {
        $role = Role::create(['name' => 'papel_qualquer_'.uniqid()]);

        foreach ($permissoes as $permisso) {
            [$module, $action] = array_pad(explode('.', $permisso, 2), 2, $permisso);

            $role->permissions()->attach(Permission::create([
                'name' => $permisso,
                'module' => $module,
                'action' => $action,
            ])->id);
        }

        $clinica['user']->assignRole($role);

        return $clinica['user']->refresh();
    }
}
