<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\TreatmentPlan;
use App\Services\Tenant\VerticalManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class VocabularioPorVerticalTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private function clinicaComVertical(string $vertical, string ...$permissoes): array
    {
        $clinica = $this->criarClinicaTeste();
        $clinica->update([
            'vertical' => $vertical,
            'verticals' => null,
        ]);

        return $this->criarUsuarioComPermissao(...$permissoes);
    }

    public function test_prontuario_fisio_mostra_atalhos_fisio(): void
    {
        [, $user] = $this->clinicaComVertical(VerticalManager::FISIOTERAPIA,
            'prontuario.view', 'avaliacao.view', 'evolucao.view', 'pacotes.view');
        $paciente = Paciente::create(['nome' => 'P', 'status' => 'ativo']);
        $this->actingAs($user)->get(route('pacientes.prontuario', $paciente))->assertOk()
            ->assertSee('Avaliações')->assertDontSee('Odontograma');
    }

    public function test_prontuario_odontologia_mantem_odontograma(): void
    {
        [, $user] = $this->clinicaComVertical(VerticalManager::ODONTOLOGIA,
            'prontuario.view', 'odontograma.view');
        $paciente = Paciente::create(['nome' => 'P', 'status' => 'ativo']);
        $this->actingAs($user)->get(route('pacientes.prontuario', $paciente))->assertOk()->assertSee('Odontograma');
    }

    public function test_prescricao_fisio_usa_profissional_logado(): void
    {
        [, $user] = $this->clinicaComVertical(VerticalManager::FISIOTERAPIA, 'prontuario.view', 'prontuario.create');
        Professional::create([
            'nome' => 'Dra. Daiane', 'tipo' => 'fisioterapeuta', 'registro_profissional' => 'CREFITO-1/123',
            'user_id' => $user->id, 'is_active' => true,
        ]);
        $paciente = Paciente::create(['nome' => 'P', 'status' => 'ativo']);
        $this->actingAs($user)->get(route('pacientes.prescricoes.index', $paciente))->assertOk()->assertSee('Dra. Daiane');
    }

    public function test_planos_fisio_oculta_dente(): void
    {
        [, $user] = $this->clinicaComVertical(VerticalManager::FISIOTERAPIA, 'tratamentos.view');
        $paciente = Paciente::create(['nome' => 'P', 'status' => 'ativo']);
        $plano = TreatmentPlan::create([
            'paciente_id' => $paciente->id, 'titulo' => 'P', 'status' => 'rascunho', 'total' => 0,
        ]);
        $this->actingAs($user)->get(route('pacientes.planos.show', [$paciente, $plano]))->assertDontSee('Dente');
    }

    public function test_planos_odontologia_mostra_dente(): void
    {
        [, $user] = $this->clinicaComVertical(VerticalManager::ODONTOLOGIA, 'tratamentos.view');
        $paciente = Paciente::create(['nome' => 'P', 'status' => 'ativo']);
        $plano = TreatmentPlan::create([
            'paciente_id' => $paciente->id, 'titulo' => 'P', 'status' => 'rascunho', 'total' => 0,
        ]);
        $this->actingAs($user)->get(route('pacientes.planos.show', [$paciente, $plano]))->assertSee('Dente');
    }

    public function test_orcamentos_fisio_oculta_dente(): void
    {
        [, $user] = $this->clinicaComVertical(VerticalManager::FISIOTERAPIA, 'orcamentos.view');
        $paciente = Paciente::create(['nome' => 'P', 'status' => 'ativo']);
        $orcamento = Budget::create(['paciente_id' => $paciente->id, 'titulo' => 'O']);
        $this->actingAs($user)->get(route('pacientes.orcamentos.show', [$paciente, $orcamento]))->assertDontSee('Dente');
    }

    public function test_profissionais_fisio_nao_pede_cro(): void
    {
        [, $user] = $this->clinicaComVertical(VerticalManager::FISIOTERAPIA, 'profissionais.view');
        $this->actingAs($user)->get(route('profissionais.create'))->assertDontSee('CRO');
    }
}
