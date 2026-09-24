<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Procedure;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedimentoTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissaoProcedimento(string ...$permissoes): User
    {
        $role = Role::create(['name' => 'procedimento_teste']);

        foreach ($permissoes as $permissao) {
            $role->permissions()->attach(Permission::create([
                'name' => $permissao,
                'module' => 'procedimentos',
                'action' => $permissao,
            ])->id);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_usuario_sem_role_nao_acessa_procedimentos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('procedimentos.index'))
            ->assertForbidden();
    }

    public function test_index_lista_procedimentos(): void
    {
        Procedure::create(['nome' => 'Limpeza']);

        $this->actingAs($this->userComPermissaoProcedimento('procedimentos.view'))
            ->get(route('procedimentos.index'))
            ->assertOk()
            ->assertSee('Limpeza');
    }

    public function test_cria_procedimento(): void
    {
        $user = $this->userComPermissaoProcedimento('procedimentos.create');

        $this->actingAs($user)
            ->post(route('procedimentos.store'), [
                'nome' => 'Radiografia Panorâmica',
                'codigo' => 'RX123',
                'valor_padrao' => 80,
                'is_active' => '1',
            ])
            ->assertRedirect(route('procedimentos.index'));

        $this->assertDatabaseHas('procedures', [
            'nome' => 'Radiografia Panorâmica',
            'codigo' => 'RX123',
        ]);
    }

    public function test_atualiza_procedimento(): void
    {
        $user = $this->userComPermissaoProcedimento('procedimentos.update');
        $procedimento = Procedure::create(['nome' => 'Extração']);

        $this->actingAs($user)
            ->put(route('procedimentos.update', $procedimento), [
                'nome' => 'Extração Simples',
                'valor_padrao' => 120,
                'is_active' => '1',
            ])
            ->assertRedirect(route('procedimentos.index'));

        $this->assertDatabaseHas('procedures', [
            'id' => $procedimento->id,
            'nome' => 'Extração Simples',
        ]);
    }
}
