<?php

namespace Tests\Feature;

use App\Models\InsuranceCompany;
use App\Models\InsurancePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class ConvenioTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_usuario_sem_role_nao_acessa_convenios(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->get(route('convenios.index'))
            ->assertForbidden();
    }

    public function test_index_lista_operadoras_com_total_de_planos(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('convenios.view');

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
        [, $user] = $this->criarUsuarioComPermissao('convenios.create');

        $this->actingAs($user)
            ->post(route('convenios.store'), [
                'nome' => 'Amil Dental',
                'is_active' => '1',
            ])
            ->assertRedirect(route('convenios.index'));

        $this->assertTenantDatabaseHas('insurance_companies', [
            'nome' => 'Amil Dental',
        ]);
    }

    public function test_atualiza_operadora(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('convenios.update');

        $operadora = InsuranceCompany::create([
            'nome' => 'OdontoCred',
        ]);

        $this->actingAs($user)
            ->put(route('convenios.update', $operadora), [
                'nome' => 'OdontoCred 2',
                'is_active' => '0',
            ])
            ->assertRedirect(route('convenios.index'));

        $this->assertTenantDatabaseHas('insurance_companies', [
            'id' => $operadora->id,
            'nome' => 'OdontoCred 2',
        ]);
    }
}
