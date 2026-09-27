<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Paciente;
use App\Models\Procedure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class OrcamentoTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private function criaPaciente(): Paciente
    {
        return Paciente::create(['nome' => 'Paciente Orçamento', 'status' => 'ativo']);
    }

    public function test_usuario_sem_permissao_nao_acessa_orcamentos(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $paciente = $this->criaPaciente();

        $this->actingAs($user)
            ->get(route('pacientes.orcamentos.index', $paciente))
            ->assertForbidden();
    }

    public function test_index_lista_orcamentos_do_paciente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('orcamentos.view');

        $paciente = $this->criaPaciente();
        $orcamento = Budget::create(['paciente_id' => $paciente->id, 'titulo' => 'Orçamento reabilitação']);

        $this->actingAs($user)
            ->get(route('pacientes.orcamentos.index', $paciente))
            ->assertOk()
            ->assertSee('Orçamento reabilitação')
            ->assertSee(route('pacientes.orcamentos.show', [$paciente, $orcamento]));
    }

    public function test_cria_orcamento_com_item(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('orcamentos.view', 'orcamentos.create');

        $paciente = $this->criaPaciente();
        $procedure = Procedure::create(['nome' => 'Clareamento', 'valor_padrao' => 250.00]);

        $this->actingAs($user)
            ->post(route('pacientes.orcamentos.store', $paciente), [
                'titulo' => 'Orçamento Clareamento',
            ])
            ->assertRedirect();

        $orcamento = Budget::where('titulo', 'Orçamento Clareamento')->firstOrFail();

        $this->actingAs($user)
            ->post(route('pacientes.orcamentos.items.store', [$paciente, $orcamento]), [
                'procedure_id' => $procedure->id,
                'quantidade' => 2,
                'valor_unitario' => 250.00,
            ])
            ->assertSessionHasNoErrors();

        $orcamento->refresh();

        $this->assertSame(1, $orcamento->items()->count());
        $this->assertSame(500.0, (float) $orcamento->total);
    }

    public function test_atualiza_status_do_orcamento(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('orcamentos.view', 'orcamentos.update');

        $paciente = $this->criaPaciente();
        $orcamento = Budget::create(['paciente_id' => $paciente->id, 'titulo' => 'Orçamento inicial', 'status' => 'emitido']);

        $this->actingAs($user)
            ->put(route('pacientes.orcamentos.update', [$paciente, $orcamento]), [
                'titulo' => 'Orçamento aprovado',
                'status' => 'aprovado',
            ])
            ->assertRedirect();

        $orcamento->refresh();

        $this->assertSame('Orçamento aprovado', $orcamento->titulo);
        $this->assertSame('aprovado', $orcamento->status);
    }

    public function test_destroy_remove_orcamento(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('orcamentos.view', 'orcamentos.delete');

        $paciente = $this->criaPaciente();
        $orcamento = Budget::create(['paciente_id' => $paciente->id, 'titulo' => 'Excluir orçamento']);

        $this->actingAs($user)
            ->delete(route('pacientes.orcamentos.destroy', [$paciente, $orcamento]))
            ->assertRedirect();

        $this->assertTenantDatabaseMissing('budgets', ['id' => $orcamento->id]);
    }

    public function test_show_exibe_itens_do_orcamento(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('orcamentos.view');

        $paciente = $this->criaPaciente();
        $procedure = Procedure::create(['nome' => 'Restauração', 'valor_padrao' => 150.00]);
        $orcamento = Budget::create(['paciente_id' => $paciente->id, 'titulo' => 'Orçamento itens']);

        $orcamento->items()->create([
            'procedure_id' => $procedure->id,
            'quantidade' => 1,
            'valor_unitario' => 150.00,
        ]);

        $this->actingAs($user)
            ->get(route('pacientes.orcamentos.show', [$paciente, $orcamento]))
            ->assertOk()
            ->assertSee('Restauração');
    }
}
