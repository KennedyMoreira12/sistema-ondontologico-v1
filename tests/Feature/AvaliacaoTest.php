<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenants;
use Tests\Concerns\PreparaClinicaFisioterapia;
use Tests\TestCase;

/**
 * Ficha de avaliação da fisioterapia.
 *
 * Duas coisas precisam estar provadas aqui: que a ficha funciona, e que ela
 * NÃO diagnostica sozinha — o conteúdo é do profissional, e uma ficha assinada
 * não muda por baixo dos panos.
 */
class AvaliacaoTest extends TestCase
{
    use CreatesTenants, PreparaClinicaFisioterapia, RefreshDatabase;

    public function test_cria_ficha_de_avaliacao(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('avaliacao.view', 'avaliacao.create');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $this->actingAs($user)
            ->post(route('pacientes.avaliacoes.store', $paciente), [
                'data' => '2026-10-20',
                'queixa_principal' => 'Dor lombar há três meses.',
                'exame_fisico' => 'Limitação de flexão.',
            ])
            ->assertRedirect();

        $this->assertTenantDatabaseHas('avaliacoes', [
            'paciente_id' => $paciente->id,
            'queixa_principal' => 'Dor lombar há três meses.',
            'status' => 'rascunho',
        ]);
    }

    public function test_avaliacao_sem_queixa_principal_e_recusada(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('avaliacao.create');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $this->actingAs($user)
            ->post(route('pacientes.avaliacoes.store', $paciente), [
                'data' => '2026-10-20',
            ])
            ->assertSessionHasErrors('queixa_principal');

        $this->assertTenantDatabaseCount('avaliacoes', 0);
    }

    public function test_avaliacao_nasce_como_rascunho_e_nao_com_diagnostico(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('avaliacao.create');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $this->actingAs($user)
            ->post(route('pacientes.avaliacoes.store', $paciente), [
                'data' => '2026-10-20',
                'queixa_principal' => 'Dor lombar.',
            ]);

        $avaliacao = Avaliacao::first();

        // O sistema não inventou conduta: os campos clínicos ficam vazios
        // enquanto ninguém escreve neles.
        $this->assertNull($avaliacao->hipotese_clinica);
        $this->assertNull($avaliacao->plano_terapeutico);
        $this->assertSame(Avaliacao::RASCUNHO, $avaliacao->status);
        $this->assertNull($avaliacao->finalizada_em);
    }

    public function test_avaliacao_finalizada_nao_pode_ser_alterada(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('avaliacao.create', 'avaliacao.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $avaliacao = Avaliacao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-20',
            'queixa_principal' => 'Dor lombar.',
            'status' => Avaliacao::RASCUNHO,
        ]);

        $this->actingAs($user)
            ->post(route('pacientes.avaliacoes.finalize', [$paciente, $avaliacao]))
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('pacientes.avaliacoes.update', [$paciente, $avaliacao]), [
                'data' => '2026-10-20',
                'queixa_principal' => 'Conteúdo trocado depois de assinada.',
            ])
            ->assertForbidden();

        $this->assertTenantDatabaseHas('avaliacoes', [
            'id' => $avaliacao->id,
            'queixa_principal' => 'Dor lombar.',
            'status' => Avaliacao::FINALIZADA,
        ]);
    }

    public function test_finalizar_gera_auditoria(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('avaliacao.create', 'avaliacao.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $avaliacao = Avaliacao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-20',
            'queixa_principal' => 'Dor lombar.',
        ]);

        $this->actingAs($user)
            ->post(route('pacientes.avaliacoes.finalize', [$paciente, $avaliacao]));

        $this->assertTenantDatabaseHas('audit_logs', [
            'acao' => 'finalizar_avaliacao',
            'tabela' => 'avaliacoes',
            'registro_id' => $avaliacao->id,
        ]);
    }

    public function test_ficha_de_um_paciente_nao_abre_pelo_outro(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('avaliacao.view');

        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);
        $outro = Paciente::create(['nome' => 'Outro Paciente']);

        $avaliacao = Avaliacao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-20',
            'queixa_principal' => 'Dor lombar.',
        ]);

        $this->actingAs($user)
            ->get(route('pacientes.avaliacoes.show', [$outro, $avaliacao]))
            ->assertNotFound();
    }

    public function test_clinica_odontologica_nao_abre_a_ficha_de_avaliacao(): void
    {
        $clinica = $this->clinicaOdontologica();
        $user = $this->usuarioComPermissao($clinica, 'avaliacao.view', 'avaliacao.create');

        $paciente = Paciente::create(['nome' => 'Paciente Odontologia']);

        $this->actingAs($user)
            ->get(route('pacientes.avaliacoes.index', $paciente))
            ->assertForbidden();
    }

    public function test_usuario_sem_permissao_nao_acessa_a_ficha(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia();
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $this->actingAs($user)
            ->get(route('pacientes.avaliacoes.index', $paciente))
            ->assertForbidden();
    }

    /**
     * Regressão: a tela não pode depender de `data` chegar como objeto.
     *
     * A view formata a data com `->format()`. Sem o cast, `data` chega como
     * string, o PHP reclama de `Call to a member function format() on string`
     * e a página inteira vira 500 — o teste falhava antes da correção.
     */
    public function test_lista_e_detalhe_da_ficha_abrem_com_as_datas_formatadas(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('avaliacao.view');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $avaliacao = Avaliacao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-20',
            'queixa_principal' => 'Dor lombar.',
            'status' => Avaliacao::FINALIZADA,
            'finalizada_em' => '2026-10-21 09:30:00',
        ]);

        $avaliacao = $avaliacao->fresh();

        $this->assertInstanceOf(Carbon::class, $avaliacao->data);
        $this->assertInstanceOf(Carbon::class, $avaliacao->finalizada_em);

        $this->actingAs($user)
            ->get(route('pacientes.avaliacoes.index', $paciente))
            ->assertOk()
            ->assertSee('20/10/2026');

        $this->actingAs($user)
            ->get(route('pacientes.avaliacoes.show', [$paciente, $avaliacao]))
            ->assertOk()
            ->assertSee('20/10/2026')
            ->assertSee('21/10/2026 09:30');
    }

    /**
     * O formulário de edição repete a data no campo `value`, pelo mesmo
     * motivo: `->format()` não pode rodar em string.
     */
    public function test_formulario_de_edicao_da_ficha_carrega_a_data(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('avaliacao.view', 'avaliacao.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $avaliacao = Avaliacao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-20',
            'queixa_principal' => 'Dor lombar.',
            'status' => Avaliacao::RASCUNHO,
        ]);

        $this->actingAs($user)
            ->get(route('pacientes.avaliacoes.edit', [$paciente, $avaliacao]))
            ->assertOk()
            ->assertSee('value="2026-10-20"', false);
    }
}
