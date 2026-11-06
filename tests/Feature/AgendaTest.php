<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\User;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Agenda do dia, da semana e do mês.
 *
 * O que estes testes protegem é o que a recepção faz o dia inteiro: marcar
 * presença/falta e reagendar sem derrubar outro atendimento. Conflito de
 * profissional, de paciente e de sala é recusado no servidor — não é só um
 * aviso na tela.
 */
class AgendaTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private Professional $profissional;

    private Room $sala;

    private Paciente $paciente;

    private User $user;

    /**
     * @var array<string, int>
     */
    private array $status = [];

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->user] = $this->criarUsuarioComPermissao(
            'agenda.view',
            'agenda.create',
            'agenda.update',
            'agenda.delete',
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
            'nome' => 'Paciente Agenda',
            'status' => 'ativo',
        ]);
    }

    private function diaFuturo(): Carbon
    {
        return now()->addDays(2)->startOfDay();
    }

    private function criarConsulta(
        Carbon $inicio,
        int $duracao = 60,
        ?Professional $profissional = null,
        ?Room $sala = null,
        ?Paciente $paciente = null,
        string $status = 'agendado',
    ): Appointment {
        return Appointment::create([
            'paciente_id' => ($paciente ?? $this->paciente)->id,
            'profissional_id' => ($profissional ?? $this->profissional)->id,
            'room_id' => ($sala ?? $this->sala)?->id,
            'appointment_status_id' => $this->status[$status],
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes($duracao),
            'titulo' => 'Consulta de teste',
            'servico' => 'Fisioterapia',
        ]);
    }

    public function test_agenda_do_dia_lista_os_atendimentos_e_mostra_os_botoes_de_presenca(): void
    {
        $dia = $this->diaFuturo();
        $this->criarConsulta($dia->copy()->setTime(9, 0));

        $this->actingAs($this->user)
            ->get(route('agenda.dia', ['data' => $dia->toDateString()]))
            ->assertOk()
            ->assertSee('Paciente Agenda')
            ->assertSee('Presente')
            ->assertSee('09:00');
    }

    public function test_marcar_presenca_altera_o_status_para_atendido(): void
    {
        $consulta = $this->criarConsulta($this->diaFuturo()->setTime(9, 0));

        $this->actingAs($this->user)
            ->post(route('agenda.presenca', $consulta), ['status' => 'atendido'])
            ->assertRedirect();

        $this->assertSame($this->status['atendido'], $consulta->refresh()->appointment_status_id);
    }

    public function test_marcar_falta_altera_o_status_para_faltou(): void
    {
        $consulta = $this->criarConsulta($this->diaFuturo()->setTime(10, 0));

        $this->actingAs($this->user)
            ->post(route('agenda.presenca', $consulta), ['status' => 'faltou'])
            ->assertRedirect();

        $this->assertSame($this->status['faltou'], $consulta->refresh()->appointment_status_id);
    }

    public function test_agendamento_com_conflito_de_profissional_e_recusado(): void
    {
        $dia = $this->diaFuturo();
        $this->criarConsulta($dia->copy()->setTime(9, 0), 60);

        $this->actingAs($this->user)
            ->post(route('agenda.store'), [
                'paciente_id' => $this->paciente->id,
                'profissional_id' => $this->profissional->id,
                'room_id' => $this->sala->id,
                'dia' => $dia->toDateString(),
                'hora_inicio' => '09:30',
                'duracao' => 60,
            ])
            ->assertSessionHasErrors('hora_inicio');

        $this->assertTenantDatabaseCount('appointments', 1);
    }

    public function test_agendamento_com_conflito_de_sala_e_recusado_mesmo_com_outro_profissional(): void
    {
        $dia = $this->diaFuturo();
        $this->criarConsulta($dia->copy()->setTime(9, 0), 60);

        $outro = Professional::create(['nome' => 'Dr. Outro', 'is_active' => true]);

        $this->actingAs($this->user)
            ->post(route('agenda.store'), [
                'paciente_id' => $this->paciente->id,
                'profissional_id' => $outro->id,
                'room_id' => $this->sala->id,
                'dia' => $dia->toDateString(),
                'hora_inicio' => '09:30',
                'duracao' => 60,
            ])
            ->assertSessionHasErrors('room_id');

        $this->assertTenantDatabaseCount('appointments', 1);
    }

    /**
     * Duplo clique ou reenvio do navegador não pode criar duas consultas no
     * mesmo horário. A chave do formulário faz o segundo envio devolver o
     * agendamento que o primeiro criou.
     */
    public function test_reenvio_do_mesmo_agendamento_nao_duplica(): void
    {
        $dia = $this->diaFuturo();

        $dados = [
            'paciente_id' => $this->paciente->id,
            'profissional_id' => $this->profissional->id,
            'room_id' => $this->sala->id,
            'dia' => $dia->toDateString(),
            'hora_inicio' => '09:00',
            'duracao' => 30,
            'titulo' => 'Pilates',
            'area' => 'ABDÔMEN',
            'servico' => 'Pilates',
            'valor' => 120,
            'retorno' => false,
            'idempotency_key' => 'agenda-unica-1',
        ];

        $this->actingAs($this->user)->post(route('agenda.store'), $dados)->assertRedirect();
        $this->actingAs($this->user)->post(route('agenda.store'), $dados)->assertRedirect();

        $this->assertTenantDatabaseCount('appointments', 1);

        $consulta = Appointment::query()->firstOrFail();

        $this->assertSame($this->sala->id, $consulta->room_id);
        $this->assertSame('Pilates', $consulta->titulo);
        $this->assertSame('Pilates', $consulta->servico);
        $this->assertSame('ABDÔMEN', $consulta->area);
        $this->assertSame('120.00', $consulta->valor);
    }

    /**
     * "Sem sala" é uma escolha válida da recepção e precisa continuar nula.
     * O serviço usa a primeira sala ativa apenas quando a chave não foi
     * enviada (caminho do WhatsApp).
     */
    public function test_agendamento_sem_sala_nao_preenche_sala_automaticamente(): void
    {
        $dia = $this->diaFuturo();

        $this->actingAs($this->user)
            ->post(route('agenda.store'), [
                'paciente_id' => $this->paciente->id,
                'profissional_id' => $this->profissional->id,
                'dia' => $dia->toDateString(),
                'hora_inicio' => '09:00',
                'duracao' => 30,
                'idempotency_key' => 'agenda-sem-sala',
            ])
            ->assertRedirect();

        $this->assertNull(Appointment::query()->firstOrFail()->room_id);
    }

    public function test_reagendamento_move_o_atendimento_e_recusa_novo_conflito(): void
    {
        $dia = $this->diaFuturo();
        $primeira = $this->criarConsulta($dia->copy()->setTime(9, 0), 60);
        $segunda = $this->criarConsulta($dia->copy()->setTime(10, 0), 60);

        $this->actingAs($this->user)
            ->patch(route('agenda.reagendar', $primeira), [
                'dia' => $dia->toDateString(),
                'hora_inicio' => '10:30',
                'duracao' => 30,
                'profissional_id' => $this->profissional->id,
                'room_id' => $this->sala->id,
            ])
            ->assertSessionHasErrors('hora_inicio');

        $this->assertSame('09:00', $primeira->refresh()->inicio->format('H:i'));

        $this->actingAs($this->user)
            ->patch(route('agenda.reagendar', $primeira), [
                'dia' => $dia->toDateString(),
                'hora_inicio' => '14:00',
                'duracao' => 30,
                'profissional_id' => $this->profissional->id,
                'room_id' => $this->sala->id,
            ])
            ->assertRedirect();

        $this->assertSame('14:00', $primeira->refresh()->inicio->format('H:i'));
        $this->assertSame('10:00', $segunda->refresh()->inicio->format('H:i'));
    }

    public function test_horarios_disponiveis_nao_incluem_horario_ocupado(): void
    {
        $dia = $this->diaFuturo();
        $this->criarConsulta($dia->copy()->setTime(9, 0), 60);

        $resposta = $this->actingAs($this->user)
            ->getJson(route('agenda.disponiveis', [
                'profissional_id' => $this->profissional->id,
                'dia' => $dia->toDateString(),
                'duracao' => 30,
            ]))
            ->assertOk();

        $horarios = $resposta->json('horarios');

        $this->assertContains('08:00', $horarios);
        $this->assertNotContains('09:00', $horarios);
        $this->assertNotContains('09:30', $horarios);
        $this->assertContains('10:00', $horarios);
    }

    public function test_agenda_mensal_mostra_o_paciente_no_dia_certo(): void
    {
        $dia = $this->diaFuturo();
        $this->criarConsulta($dia->copy()->setTime(11, 0));

        $this->actingAs($this->user)
            ->get(route('agenda.mes', ['data' => $dia->toDateString()]))
            ->assertOk()
            ->assertSee('Paciente Agenda');
    }
}
