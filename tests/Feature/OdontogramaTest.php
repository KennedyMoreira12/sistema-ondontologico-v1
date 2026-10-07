<?php

namespace Tests\Feature;

use App\Models\OdontogramToothCondition;
use App\Models\Paciente;
use App\Models\ToothCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class OdontogramaTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private function criarPaciente(string $nome = 'Maria Oliveira'): Paciente
    {
        return Paciente::create(['nome' => $nome]);
    }

    private function criarCondicao(string $name = 'Cárie'): ToothCondition
    {
        return ToothCondition::create(['name' => $name, 'cor' => '#dc3545']);
    }

    public function test_usuario_sem_role_nao_acessa_odontograma(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();
        $paciente = $this->criarPaciente();

        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente))
            ->assertForbidden();
    }

    public function test_usuario_com_permissao_de_visualizacao_acessa_o_editor(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.view');
        $paciente = $this->criarPaciente('José da Silva');

        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente))
            ->assertOk()
            ->assertSee('José da Silva')
            ->assertSee('odontograma-arcadas');
    }

    public function test_usuario_sem_permissao_de_edicao_nao_altera_odontograma(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.view');
        $paciente = $this->criarPaciente();
        $condicao = $this->criarCondicao();

        $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 16]), [
                'tooth_condition_id' => $condicao->id,
                'camada' => 'existente',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson(route('pacientes.odontograma.tooth.update', [$paciente, 'dente' => 16]), [
                'status' => 'ausente',
            ])
            ->assertForbidden();

        $this->assertTenantDatabaseMissing('odontogram_tooth_conditions', [
            'tooth_condition_id' => $condicao->id,
        ]);
    }

    public function test_payload_inclui_todos_os_dentes_da_denticao_permanente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.view');
        $paciente = $this->criarPaciente();

        $this->actingAs($user)
            ->getJson(route('pacientes.odontograma.dados', $paciente))
            ->assertOk()
            ->assertJsonCount(32, 'dentes')
            ->assertJsonPath('odontogram.denticao', 'permanente');
    }

    public function test_adiciona_condicao_em_superficie_do_dente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();
        $condicao = $this->criarCondicao('Cárie oclusal');

        $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 16]), [
                'tooth_condition_id' => $condicao->id,
                'superficie' => 'mesial',
                'camada' => 'existente',
                'descricao' => 'Cárie na região mesial',
            ])
            ->assertCreated()
            ->assertJsonFragment(['superficie' => 'mesial', 'camada' => 'existente']);

        $this->assertTenantDatabaseHas('odontogram_tooth_conditions', [
            'tooth_condition_id' => $condicao->id,
            'superficie' => 'mesial',
            'camada' => 'existente',
            'descricao' => 'Cárie na região mesial',
        ]);
    }

    public function test_condicao_sem_superficie_vale_para_o_dente_inteiro(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();
        $condicao = $this->criarCondicao('Dente ausente');

        $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 26]), [
                'tooth_condition_id' => $condicao->id,
                'camada' => 'planejado',
            ])
            ->assertCreated()
            ->assertJsonPath('condicoes.0.camada', 'planejado')
            ->assertJsonPath('condicoes.0.superficie', null);

        $this->assertTenantDatabaseHas('odontogram_tooth_conditions', [
            'tooth_condition_id' => $condicao->id,
            'superficie' => null,
            'camada' => 'planejado',
        ]);
    }

    public function test_rejeita_superficie_nao_aplicavel_ao_dente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();
        $condicao = $this->criarCondicao();

        $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 12]), [
                'tooth_condition_id' => $condicao->id,
                'superficie' => 'oclusal',
                'camada' => 'existente',
            ])
            ->assertUnprocessable();
    }

    public function test_rejeita_camada_inexistente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();
        $condicao = $this->criarCondicao();

        $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 16]), [
                'tooth_condition_id' => $condicao->id,
                'camada' => 'hialino',
            ])
            ->assertUnprocessable();
    }

    public function test_rejeita_condicao_inexistente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();

        $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 16]), [
                'tooth_condition_id' => 99999,
                'camada' => 'existente',
            ])
            ->assertUnprocessable();
    }

    public function test_rejeita_dente_com_numero_fdi_invalido(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();
        $condicao = $this->criarCondicao();

        $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 999]), [
                'tooth_condition_id' => $condicao->id,
                'camada' => 'existente',
            ])
            ->assertStatus(422);
    }

    public function test_atualiza_status_e_observacao_do_dente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();

        $this->actingAs($user)
            ->putJson(route('pacientes.odontograma.tooth.update', [$paciente, 'dente' => 48]), [
                'status' => 'ausente',
                'observacao' => 'Exodontia indicada',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ausente');

        $this->assertTenantDatabaseHas('odontogram_teeth', [
            'dente' => 48,
            'status' => 'ausente',
            'observacao' => 'Exodontia indicada',
        ]);
    }

    public function test_remove_condicao_do_dente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();
        $condicao = $this->criarCondicao('Restauração');

        $resposta = $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 36]), [
                'tooth_condition_id' => $condicao->id,
                'camada' => 'existente',
            ])
            ->assertCreated();

        $condicaoRegistrada = OdontogramToothCondition::first();

        $this->actingAs($user)
            ->deleteJson(route('pacientes.odontograma.condition.destroy', [
                $paciente, 'dente' => 36, 'condicao' => $condicaoRegistrada->id,
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'condicoes');

        $this->assertTenantDatabaseMissing('odontogram_tooth_conditions', ['id' => $condicaoRegistrada->id]);
    }

    public function test_limpa_registro_do_dente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();
        $condicao = $this->criarCondicao();

        $odontogram = $paciente->odontogramas()->create(['titulo' => 'Odontograma principal']);
        $dente = $odontogram->dentes()->create(['dente' => 46, 'status' => 'presente', 'observacao' => 'Obs']);
        $dente->condicoes()->create(['tooth_condition_id' => $condicao->id, 'camada' => 'existente']);

        $this->actingAs($user)
            ->deleteJson(route('pacientes.odontograma.tooth.destroy', [$paciente, 'dente' => 46]))
            ->assertOk()
            ->assertJsonPath('status', 'presente')
            ->assertJsonPath('observacao', null)
            ->assertJsonCount(0, 'condicoes');

        $this->assertTenantDatabaseMissing('odontogram_tooth_conditions', ['odontogram_tooth_id' => $dente->id]);
        $this->assertTenantDatabaseHas('odontogram_teeth', [
            'id' => $dente->id,
            'status' => 'presente',
            'observacao' => null,
        ]);
    }

    public function test_atualiza_configuracoes_do_odontograma(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();

        $this->actingAs($user)
            ->putJson(route('pacientes.odontograma.settings', $paciente), [
                'denticao' => 'decidua',
                'notacao' => 'Universal',
                'notas' => 'Acompanhar erupção do segundo molar.',
            ])
            ->assertOk()
            ->assertJsonPath('odontogram.denticao', 'decidua')
            ->assertJsonPath('odontogram.notacao', 'Universal');

        $this->assertTenantDatabaseHas('odontograms', [
            'denticao' => 'decidua',
            'notacao' => 'Universal',
            'notas' => 'Acompanhar erupção do segundo molar.',
        ]);
    }

    public function test_denticao_mista_combina_arcadas_permanente_e_decidua(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.view', 'odontograma.update');
        $paciente = $this->criarPaciente();

        $this->actingAs($user)
            ->putJson(route('pacientes.odontograma.settings', $paciente), ['denticao' => 'mista'])
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('pacientes.odontograma.dados', $paciente))
            ->assertOk()
            ->assertJsonCount(52, 'dentes');
    }

    public function test_historico_lista_as_alteracoes_do_odontograma(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.view', 'odontograma.update');
        $paciente = $this->criarPaciente('Paulo Lima');
        $condicao = $this->criarCondicao('Cárie');

        $this->actingAs($user)
            ->putJson(route('pacientes.odontograma.settings', $paciente), ['notas' => 'Primeira consulta.'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 18]), [
                'tooth_condition_id' => $condicao->id,
                'camada' => 'existente',
            ])
            ->assertCreated();

        $acoes = $this->actingAs($user)
            ->getJson(route('pacientes.odontograma.historico', $paciente))
            ->assertOk()
            ->json('historico');

        $acoes = collect($acoes)->pluck('acao');
        $this->assertTrue($acoes->contains('adicionar_condicao_dente'));
        $this->assertTrue($acoes->contains('atualizar_odontograma'));
    }

    public function test_historico_registra_profissional_logado(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.view', 'odontograma.update');
        $paciente = $this->criarPaciente();
        $condicao = $this->criarCondicao();

        $this->actingAs($user)
            ->postJson(route('pacientes.odontograma.condition.store', [$paciente, 'dente' => 24]), [
                'tooth_condition_id' => $condicao->id,
                'camada' => 'existente',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->getJson(route('pacientes.odontograma.historico', $paciente))
            ->assertOk()
            ->assertJsonPath('historico.0.profissional', $user->name);
    }

    public function test_denticao_invalida_na_configuracao_e_rejeitada(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('odontograma.update');
        $paciente = $this->criarPaciente();

        $this->actingAs($user)
            ->putJson(route('pacientes.odontograma.settings', $paciente), ['denticao' => 'transitoria'])
            ->assertUnprocessable();
    }
}
