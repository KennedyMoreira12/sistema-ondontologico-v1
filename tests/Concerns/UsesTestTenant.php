<?php

namespace Tests\Concerns;

use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;

/**
 * Clínica de teste para quem só precisa de UMA clínica por teste.
 *
 * O `TestCase` base já deixa a conexão `tenant` apontada para um banco com o
 * schema pronto, então aqui basta criar o registro central da clínica e um
 * usuário. Para testes com duas ou mais clínicas use `CreatesTenants`, que
 * precisa de um banco por clínica.
 */
trait UsesTestTenant
{
    use SubscribesPlan;

    protected ?Clinic $clinicaTest = null;

    /**
     * Cria a clínica e conecta no banco dela, como o middleware faz em produção.
     */
    protected function criarClinicaTeste(string $name = 'Clínica Teste', string $status = 'active'): Clinic
    {
        $clinic = Clinic::create([
            'name' => $name,
            'email' => 'contato@'.uniqid().'.test',
            'status' => $status,
            'is_active' => true,
        ]);

        $clinic->tenantConnection()->create([
            'driver' => 'sqlite',
            'database_name' => $this->tenantFile,
            'is_active' => true,
            'last_migrated_at' => now(),
        ]);

        $this->conectar($clinic);

        return $this->clinicaTest = $clinic;
    }

    /**
     * Usuário autenticado de uma clínica, com um papel que concede exatamente as
     * permissões pedidas.
     *
     * @return array{Clinic, User}
     */
    protected function criarUsuarioComPermissao(string ...$permissoes): array
    {
        $clinic = $this->clinicaTest ?? $this->criarClinicaTeste();

        $user = User::factory()->forClinic($clinic->id)->create();

        $user->assignRole($this->criarRoleComPermissoes(...$permissoes));

        return [$clinic, $user];
    }

    /**
     * Usuário autenticado e SEM nenhum papel, para exercitar o bloqueio por
     * falta de permissão.
     *
     * @return array{Clinic, User}
     */
    protected function criarUsuarioSemPapel(): array
    {
        $clinic = $this->clinicaTest ?? $this->criarClinicaTeste();

        return [$clinic, User::factory()->forClinic($clinic->id)->create()];
    }

    protected function criarRoleComPermissoes(string ...$permissoes): Role
    {
        $role = Role::create(['name' => 'papel_'.uniqid()]);

        foreach ($permissoes as $permissao) {
            [$module, $action] = array_pad(explode('.', $permissao, 2), 2, $permissao);

            $role->permissions()->attach(Permission::create([
                'name' => $permissao,
                'module' => $module,
                'action' => $action,
            ])->id);
        }

        return $role;
    }

    protected function conectar(Clinic $clinic): void
    {
        app(TenantManager::class)->set($clinic);
        app(TenantDatabaseManager::class)->connectFor($clinic->refresh());
    }
}
