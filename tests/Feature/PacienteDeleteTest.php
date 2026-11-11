<?php

namespace Tests\Feature;

use App\Models\Central\User;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Exclusão de paciente pela ficha e pela lista.
 *
 * A exclusão é um soft delete: o registro sai da lista, mas o histórico
 * clínico continua no banco e pode ser auditado.
 */
class PacienteDeleteTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$clinica, $this->user] = $this->criarUsuarioComPermissao(
            'pacientes.view',
            'pacientes.update',
            'pacientes.delete',
        );

        $this->conectar($clinica);
    }

    protected function criarPaciente(string $nome = 'Paciente Excluível'): Paciente
    {
        return Paciente::create([
            'nome' => $nome,
            'cpf' => '123.456.789-'.mt_rand(10, 99),
            'status' => 'ativo',
        ]);
    }

    /** @test */
    public function test_ficha_mostra_botao_excluir_para_quem_tem_permissao(): void
    {
        $paciente = $this->criarPaciente();

        $response = $this->actingAs($this->user)->get(route('pacientes.show', $paciente));

        $response->assertStatus(200);
        $response->assertSee('Excluir');
        $response->assertSee(route('pacientes.destroy', $paciente), false);
    }

    /** @test */
    public function test_lista_mostra_botao_excluir_para_quem_tem_permissao(): void
    {
        $paciente = $this->criarPaciente();

        $response = $this->actingAs($this->user)->get(route('pacientes.index'));

        $response->assertStatus(200);
        $response->assertSee(route('pacientes.destroy', $paciente), false);
    }

    /** @test */
    public function test_exclui_paciente_com_soft_delete(): void
    {
        $paciente = $this->criarPaciente();

        $response = $this->actingAs($this->user)
            ->delete(route('pacientes.destroy', $paciente));

        $response->assertRedirect(route('pacientes.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('pacientes', ['id' => $paciente->id], 'tenant');
        $this->assertSame('inativo', Paciente::withTrashed()->find($paciente->id)->status);
    }

    /** @test */
    public function test_usuario_sem_permissao_nao_exclui_paciente(): void
    {
        $paciente = $this->criarPaciente();

        [, $semPermissao] = $this->criarUsuarioSemPapel();

        $response = $this->actingAs($semPermissao)
            ->delete(route('pacientes.destroy', $paciente));

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('pacientes', ['id' => $paciente->id], 'tenant');
    }
}
