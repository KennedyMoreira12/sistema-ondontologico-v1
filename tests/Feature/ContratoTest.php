<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Paciente;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratoTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissaoContrato(string ...$permissoes): User
    {
        $role = Role::create(['name' => 'contrato_teste']);

        foreach ($permissoes as $permissao) {
            $role->permissions()->attach(Permission::create([
                'name' => $permissao,
                'module' => 'contratos',
                'action' => $permissao,
            ])->id);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function createPaciente(): Paciente
    {
        return Paciente::create(['nome' => 'Maria Silva', 'celular' => '(11) 99999-0000']);
    }

    public function test_usuario_sem_role_nao_acessa_contratos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('contratos.index'))
            ->assertForbidden();
    }

    public function test_index_lista_contratos(): void
    {
        $paciente = $this->createPaciente();
        Contract::create([
            'paciente_id' => $paciente->id,
            'tipo' => 'consentimento',
            'status' => 'assinado',
            'assinado_em' => now(),
        ]);

        $this->actingAs($this->userComPermissaoContrato('contratos.view'))
            ->get(route('contratos.index'))
            ->assertOk()
            ->assertSee('Maria Silva');
    }

    public function test_cria_contrato(): void
    {
        $user = $this->userComPermissaoContrato('contratos.create');
        $paciente = $this->createPaciente();

        $this->actingAs($user)
            ->post(route('contratos.store'), [
                'paciente_id' => $paciente->id,
                'tipo' => 'tratamento',
                'status' => 'pendente',
                'conteudo' => 'Termo de autorização de tratamento.',
            ])
            ->assertRedirect(route('contratos.index'));

        $this->assertDatabaseHas('contracts', [
            'paciente_id' => $paciente->id,
            'tipo' => 'tratamento',
            'status' => 'pendente',
            'user_id' => $user->id,
        ]);
    }

    public function test_atualiza_contrato(): void
    {
        $user = $this->userComPermissaoContrato('contratos.update');
        $paciente = $this->createPaciente();
        $contrato = Contract::create([
            'paciente_id' => $paciente->id,
            'tipo' => 'consentimento',
            'status' => 'pendente',
        ]);

        $this->actingAs($user)
            ->put(route('contratos.update', $contrato), [
                'paciente_id' => $paciente->id,
                'tipo' => 'consentimento',
                'status' => 'assinado',
                'assinado_em' => '2026-09-24T15:30',
            ])
            ->assertRedirect(route('contratos.index'));

        $this->assertDatabaseHas('contracts', [
            'id' => $contrato->id,
            'status' => 'assinado',
        ]);
    }

    public function test_exclui_contrato(): void
    {
        $user = $this->userComPermissaoContrato('contratos.delete');
        $paciente = $this->createPaciente();
        $contrato = Contract::create([
            'paciente_id' => $paciente->id,
            'tipo' => 'outros',
            'status' => 'cancelado',
        ]);

        $this->actingAs($user)
            ->delete(route('contratos.destroy', $contrato))
            ->assertRedirect(route('contratos.index'));

        $this->assertDatabaseMissing('contracts', ['id' => $contrato->id]);
    }
}
