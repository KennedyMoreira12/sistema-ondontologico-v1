<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\EvolucaoSessao;
use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\Concerns\PreparaClinicaFisioterapia;
use Tests\TestCase;

/**
 * Evolução por sessão de fisioterapia.
 *
 * A evolução registra o que aconteceu NA sessão. Ela nasce em rascunho (que é
 * editável) e só vira definitiva quando o profissional assina — e definitiva
 * é imutável, porque é a assinatura que dá peso à auditoria.
 */
class EvolucaoSessaoTest extends TestCase
{
    use CreatesTenants, PreparaClinicaFisioterapia, RefreshDatabase;

    public function test_cria_evolucao_por_sessao(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('evolucao.view', 'evolucao.create');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $avaliacao = Avaliacao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-20',
            'queixa_principal' => 'Dor lombar.',
        ]);

        $this->actingAs($user)
            ->post(route('pacientes.evolucoes-sessao.store', $paciente), [
                'data' => '2026-10-20',
                'avaliacao_id' => $avaliacao->id,
                'conteudo' => 'Sessão 1: melhora de amplitude.',
                'dor_antes' => 7,
                'dor_depois' => 4,
            ])
            ->assertRedirect(route('pacientes.evolucoes-sessao.index', $paciente));

        $this->assertTenantDatabaseHas('evolucoes_sessao', [
            'paciente_id' => $paciente->id,
            'avaliacao_id' => $avaliacao->id,
            'conteudo' => 'Sessão 1: melhora de amplitude.',
            'dor_antes' => 7,
            'dor_depois' => 4,
            'status' => EvolucaoSessao::RASCUNHO,
        ]);
    }

    public function test_evolucao_sem_conteudo_e_recusada(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('evolucao.create');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $this->actingAs($user)
            ->post(route('pacientes.evolucoes-sessao.store', $paciente), [
                'data' => '2026-10-20',
            ])
            ->assertSessionHasErrors('conteudo');

        $this->assertTenantDatabaseCount('evolucoes_sessao', 0);
    }

    public function test_dor_fora_da_escala_e_recusada(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('evolucao.create');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $this->actingAs($user)
            ->post(route('pacientes.evolucoes-sessao.store', $paciente), [
                'data' => '2026-10-20',
                'conteudo' => 'Sessão 1.',
                'dor_antes' => 44,
            ])
            ->assertSessionHasErrors('dor_antes');

        $this->assertTenantDatabaseCount('evolucoes_sessao', 0);
    }

    public function test_evolucao_finalizada_nao_pode_ser_alterada(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('evolucao.create', 'evolucao.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $evolucao = EvolucaoSessao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-20',
            'conteudo' => 'Sessão 1.',
        ]);

        $this->actingAs($user)
            ->post(route('pacientes.evolucoes-sessao.finalize', [$paciente, $evolucao]))
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('pacientes.evolucoes-sessao.update', [$paciente, $evolucao]), [
                'data' => '2026-10-20',
                'conteudo' => 'Conteúdo trocado depois de assinado.',
            ])
            ->assertForbidden();

        $this->assertTenantDatabaseHas('evolucoes_sessao', [
            'id' => $evolucao->id,
            'conteudo' => 'Sessão 1.',
            'status' => EvolucaoSessao::FINALIZADA,
        ]);
    }

    public function test_evolucao_gera_auditoria(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('evolucao.create', 'evolucao.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $evolucao = EvolucaoSessao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-20',
            'conteudo' => 'Sessão 1.',
        ]);

        $this->actingAs($user)
            ->post(route('pacientes.evolucoes-sessao.finalize', [$paciente, $evolucao]));

        $this->assertTenantDatabaseHas('audit_logs', [
            'acao' => 'finalizar_evolucao_sessao',
            'tabela' => 'evolucoes_sessao',
            'registro_id' => $evolucao->id,
        ]);
    }

    public function test_evolucao_de_um_paciente_nao_abre_pelo_outro(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('evolucao.view', 'evolucao.update');

        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);
        $outro = Paciente::create(['nome' => 'Outro Paciente']);

        $evolucao = EvolucaoSessao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-20',
            'conteudo' => 'Sessão 1.',
        ]);

        $this->actingAs($user)
            ->put(route('pacientes.evolucoes-sessao.update', [$outro, $evolucao]), [
                'data' => '2026-10-20',
                'conteudo' => 'Invadindo ficha de outro paciente.',
            ])
            ->assertNotFound();
    }

    public function test_clinica_odontologica_nao_abre_a_evolucao(): void
    {
        $clinica = $this->clinicaOdontologica();
        $user = $this->usuarioComPermissao($clinica, 'evolucao.view', 'evolucao.create');

        $paciente = Paciente::create(['nome' => 'Paciente Odontologia']);

        $this->actingAs($user)
            ->get(route('pacientes.evolucoes-sessao.index', $paciente))
            ->assertForbidden();
    }
}
