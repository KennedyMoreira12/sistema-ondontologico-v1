<?php

namespace Tests\Feature;

use App\Models\Procedure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class ProcedimentoTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_usuario_sem_role_nao_acessa_procedimentos(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->get(route('procedimentos.index'))
            ->assertForbidden();
    }

    public function test_index_lista_procedimentos(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('procedimentos.view');

        Procedure::create(['nome' => 'Limpeza']);

        $this->actingAs($user)
            ->get(route('procedimentos.index'))
            ->assertOk()
            ->assertSee('Limpeza');
    }

    public function test_cria_procedimento(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('procedimentos.create');

        $this->actingAs($user)
            ->post(route('procedimentos.store'), [
                'nome' => 'Radiografia Panorâmica',
                'codigo' => 'RX123',
                'valor_padrao' => 80,
                'is_active' => '1',
            ])
            ->assertRedirect(route('procedimentos.index'));

        $this->assertTenantDatabaseHas('procedures', [
            'nome' => 'Radiografia Panorâmica',
            'codigo' => 'RX123',
        ]);
    }

    public function test_atualiza_procedimento(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('procedimentos.update');

        $procedimento = Procedure::create(['nome' => 'Extração']);

        $this->actingAs($user)
            ->put(route('procedimentos.update', $procedimento), [
                'nome' => 'Extração Simples',
                'valor_padrao' => 120,
                'is_active' => '1',
            ])
            ->assertRedirect(route('procedimentos.index'));

        $this->assertTenantDatabaseHas('procedures', [
            'id' => $procedimento->id,
            'nome' => 'Extração Simples',
        ]);
    }
}
