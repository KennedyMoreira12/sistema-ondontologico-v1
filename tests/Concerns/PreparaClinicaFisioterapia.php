<?php

namespace Tests\Concerns;

use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Tenant\VerticalManager;

/**
 * Preparo das clínicas fictícias usadas nos testes da fisioterapia.
 *
 * São DUAS clínicas de mentira: uma de odontologia e uma de fisioterapia. O
 * que os testes precisam provar é sempre duplo — que a fisioterapia
 * funciona, e que a odontologia continua exatamente como estava.
 */
trait PreparaClinicaFisioterapia
{
    /**
     * Clínica de odontologia (a vertical padrão, como a Malheiro).
     *
     * @return array{clinic: Clinic, user: ?User, token: string}
     */
    protected function clinicaOdontologica(): array
    {
        return $this->criarClinica('Clínica Odontológica');
    }

    /**
     * Clínica de fisioterapia, com um usuário que tem as permissões pedidas.
     *
     * @return array{clinica: array{clinic: Clinic, user: ?User, token: string}, user: User}
     */
    protected function clinicaFisioterapia(string ...$permissoes): array
    {
        $clinica = $this->criarClinica('Clínica de Fisioterapia');
        $clinica['clinic']->update(['vertical' => VerticalManager::FISIOTERAPIA]);

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
        $role = Role::create(['name' => 'papel_fisio_'.uniqid()]);

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
