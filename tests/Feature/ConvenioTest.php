<?php

namespace Tests\Feature;

use App\Models\InsuranceCompany;
use App\Models\InsurancePlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvenioTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissaoConvenio(string ...$permissoes): User
    {
        $role = Role::create(['name' => 'convenio_teste']);

        foreach ($permissoes as $permissao) {
            $permission = Permission::create([
                'name' => $permissao,
                'module' => 'convenios',
                'action' => $permissao,
            ]);

            $role->permissions()->attach($permission->id);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_usuario_sem_role_nao_acessa_convenios(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('convenios.index'))
            ->assertForbidden();
    }

    public function test_index_lista_operadoras_com_total_de_planos(): void
    {
        $user = $this->userComPermissaoConvenio('convenios.view');

        $operadora = InsuranceCompany::create([
            'nome' => 'OdontoCred',
            'cnpj' => null,
        ]);

        InsurancePlan::create([
            'insurance_company_id' => $operadora->id,
            'nome' => 'Plano Ouro',
        ]);

        $this->actingAs($user)
            ->get(route('convenios.index'))
            ->assertOk()
            ->assertSee('OdontoCred')
            ->assertSee('1');
    }

    public function test_cria_operadora(): void
    {
        $user = $this->userComPermissaoConvenio('convenios.create');

        $this->actingAs($user)
            ->post(route('convenios.store'), [
                'nome' => 'Amil Dental',
                'is_active' => '1',
            ])
            ->assertRedirect(route('convenios.index'));

        $this->assertDatabaseHas('insurance_companies', [
            'nome' => 'Amil Dental',
        ]);
    }

    public function test_atualiza_operadora(): void
    {
        $user = $this->userComPermissaoConvenio('convenios.update');

        $operadora = InsuranceCompany::create([
            'nome' => 'OdontoCred',
        ]);

        $this->actingAs($user)
            ->put(route('convenios.update', $operadora), [
                'nome' => 'OdontoCred 2',
                'is_active' => '0',
            ])
            ->assertRedirect(route('convenios.index'));

        $this->assertDatabaseHas('insurance_companies', [
            'id' => $operadora->id,
            'nome' => 'OdontoCred 2',
        ]);
    }
}
