<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\User;
use App\Models\ConsumoSessao;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use App\Models\Payment;
use App\Models\Professional;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Atendimento do dia: presença, falta e consumo de sessão.
 *
 * O consumo é idempotente por atendimento: dois cliques em "consumir" não
 * descontam duas sessões. A falta nunca debita sozinha — reposição é ato
 * explícito, porque a regra comercial da cliente ainda não foi confirmada.
 */
class AtendimentoTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private User $user;

    private Professional $profissional;

    private Room $sala;

    private Paciente $paciente;

    /**
     * @var array<string, int>
     */
    private array $status = [];

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->user] = $this->criarUsuarioComPermissao(
            'agenda.view',
            'agenda.update',
            'pacientes.view',
        );

        foreach ([
            'agendado' => 'Agendado',
            'confirmado' => 'Confirmado',
            'em_atendimento' => 'Em atendimento',
            'atendido' => 'Atendido',
            'faltou' => 'Faltou',
            'cancelado' => 'Cancelado',
        ] as $nome => $rotulo) {
            $this->status[$nome] = AppointmentStatus::create([
                'name' => $nome,
                'label' => $rotulo,
            ])->id;
        }

        $this->profissional = Professional::create([
            'nome' => 'Dra. Daiane',
            'is_active' => true,
        ]);

        $this->sala = Room::create([
            'nome' => 'Sala 1',
            'is_active' => true,
        ]);

        $this->paciente = Paciente::create([
            'nome' => 'Paciente Atendimento',
            'status' => 'ativo',
        ]);
    }

    private function criarConsulta(string $status = 'agendado', ?Paciente $paciente = null): Appointment
    {
        $inicio = today()->setTime(9, 0);

        return Appointment::create([
            'paciente_id' => ($paciente ?? $this->paciente)->id,
            'profissional_id' => $this->profissional->id,
            'room_id' => $this->sala->id,
            'appointment_status_id' => $this->status[$status],
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addHour(),
            'titulo' => 'Sessão de Pilates',
            'servico' => 'Pilates',
            'area' => 'Abdômen',
            'valor' => 120,
        ]);
    }

    private function criarPacote(?Paciente $paciente = null, int $usadas = 0): PacoteSessao
    {
        return PacoteSessao::create([
            'paciente_id' => ($paciente ?? $this->paciente)->id,
            'profissional_id' => $this->profissional->id,
            'nome' => 'Pacote Pilates 10 sessões',
            'servico' => 'Pilates',
            'total_sessoes' => 10,
            'sessoes_usadas' => $usadas,
            'valor_sessao' => 120,
            'valor_total' => 1200,
        ]);
    }

    public function test_tela_do_dia_lista_paciente_e_saldo_do_pacote(): void
    {
        $consulta = $this->criarConsulta();
        $this->criarPacote(usadas: 2);

        $this->actingAs($this->user)
            ->get(route('atendimentos.index', ['data' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Paciente Atendimento')
            ->assertSee('8/10 restantes')
            ->assertSee('Sessão de Pilates')
            ->assertSee(route('pacientes.historico', $consulta->paciente), escape: false);
    }

    public function test_presenca_e_consumo_debitam_a_sessao_uma_unica_vez(): void
    {
        $consulta = $this->criarConsulta();
        $pacote = $this->criarPacote();

        $this->actingAs($this->user)
            ->post(route('agenda.presenca', $consulta), ['status' => 'atendido'])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('atendimentos.consumir', $consulta), ['pacote_id' => $pacote->id])
            ->assertRedirect();

        $this->assertSame(1, $pacote->refresh()->sessoes_usadas);

        $consumo = ConsumoSessao::firstOrFail();
        $this->assertSame($consulta->id, $consumo->appointment_id);
        $this->assertSame($pacote->id, $consumo->pacote_id);
        $this->assertSame($this->paciente->id, $consumo->paciente_id);

        $this->actingAs($this->user)
            ->post(route('atendimentos.consumir', $consulta), ['pacote_id' => $pacote->id])
            ->assertRedirect();

        $this->assertSame(1, $pacote->refresh()->sessoes_usadas);
        $this->assertTenantDatabaseCount('consumo_sessao', 1);
    }

    public function test_consumo_exige_presenca_registrada(): void
    {
        $consulta = $this->criarConsulta();
        $pacote = $this->criarPacote();

        $this->actingAs($this->user)
            ->post(route('atendimentos.consumir', $consulta), ['pacote_id' => $pacote->id])
            ->assertSessionHasErrors('pacote_id');

        $this->assertSame(0, $pacote->refresh()->sessoes_usadas);
        $this->assertTenantDatabaseCount('consumo_sessao', 0);
    }

    public function test_falta_nao_consome_sessao(): void
    {
        $consulta = $this->criarConsulta();
        $pacote = $this->criarPacote();

        $this->actingAs($this->user)
            ->post(route('agenda.presenca', $consulta), ['status' => 'faltou'])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('atendimentos.consumir', $consulta), ['pacote_id' => $pacote->id])
            ->assertSessionHasErrors('pacote_id');

        $this->assertSame(0, $pacote->refresh()->sessoes_usadas);
        $this->assertTenantDatabaseCount('consumo_sessao', 0);
        $this->assertSame($this->status['faltou'], $consulta->refresh()->appointment_status_id);
    }

    /**
     * Uma vez debitada a sessão, o atendimento não pode virar falta: o
     * extrato ficaria contando uma sessão de um atendimento que não ocorreu.
     * Para corrigir, a sessão precisa ser estornada antes.
     */
    public function test_nao_permite_marcar_falta_depois_de_consumir_sessao(): void
    {
        $consulta = $this->criarConsulta();
        $pacote = $this->criarPacote();

        $this->actingAs($this->user)
            ->post(route('agenda.presenca', $consulta), ['status' => 'atendido'])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('atendimentos.consumir', $consulta), ['pacote_id' => $pacote->id])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('agenda.presenca', $consulta), ['status' => 'faltou'])
            ->assertSessionHasErrors('status');

        $consulta->refresh();

        $this->assertSame($this->status['atendido'], $consulta->appointment_status_id);
        $this->assertSame(1, $pacote->refresh()->sessoes_usadas);
        $this->assertTenantDatabaseCount('consumo_sessao', 1);
    }

    public function test_pacote_de_outro_paciente_nao_e_consumido(): void
    {
        $consulta = $this->criarConsulta('atendido');
        $outro = Paciente::create(['nome' => 'Outro Paciente', 'status' => 'ativo']);
        $pacoteDoOutro = $this->criarPacote($outro);

        $this->actingAs($this->user)
            ->post(route('atendimentos.consumir', $consulta), ['pacote_id' => $pacoteDoOutro->id])
            ->assertSessionHasErrors('pacote_id');

        $this->assertSame(0, $pacoteDoOutro->refresh()->sessoes_usadas);
        $this->assertTenantDatabaseCount('consumo_sessao', 0);
    }

    public function test_historico_do_paciente_reune_consultas_pacotes_e_pagamentos(): void
    {
        $this->criarConsulta('atendido');
        $this->criarPacote(usadas: 3);

        Payment::create([
            'paciente_id' => $this->paciente->id,
            'data' => '2026-10-10',
            'valor' => 300,
            'servico' => 'Pilates',
        ]);

        $this->actingAs($this->user)
            ->get(route('pacientes.historico', $this->paciente))
            ->assertOk()
            ->assertSee('Paciente Atendimento')
            ->assertSee('Pacote Pilates 10 sessões')
            ->assertSee('7 restantes')
            ->assertSee('R$ 300,00')
            ->assertSee('Pilates')
            ->assertSee('Abdômen')
            ->assertSee('Presenças');
    }

    public function test_usuario_sem_permissao_nao_acessa_atendimentos(): void
    {
        [, $semPapel] = $this->criarUsuarioSemPapel();

        $this->actingAs($semPapel)
            ->get(route('atendimentos.index'))
            ->assertForbidden();
    }
}
