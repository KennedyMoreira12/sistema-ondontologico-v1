<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Clinica;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.api_token' => 'token-teste']);
    }

    private function criarContexto(): Clinica
    {
        $clinica = Clinica::create(['nome' => 'Clínica Teste']);
        Professional::create([
            'clinica_id' => $clinica->id,
            'nome' => 'Dra. Ana',
            'is_active' => true,
        ]);
        Room::create(['nome' => 'Sala 1', 'is_active' => true]);

        foreach (['agendado', 'confirmado', 'cancelado'] as $name) {
            AppointmentStatus::create(['name' => $name, 'label' => ucfirst($name)]);
        }

        return $clinica;
    }

    public function test_requisicao_sem_token_e_rejeitada(): void
    {
        $this->getJson(route('api.whatsapp.info'))
            ->assertUnauthorized();
    }

    public function test_requisicao_com_token_invalido_e_rejeitada(): void
    {
        $this->withHeader('X-WhatsApp-Token', 'errado')
            ->getJson(route('api.whatsapp.info'))
            ->assertUnauthorized();
    }

    public function test_info_retorna_status_da_agenda(): void
    {
        $this->criarContexto();

        $this->withHeader('X-WhatsApp-Token', 'token-teste')
            ->getJson(route('api.whatsapp.info'))
            ->assertOk()
            ->assertJsonPath('clinica.nome', 'Clínica Teste')
            ->assertJsonCount(3, 'statuses');
    }

    public function test_cria_e_retorna_paciente_por_telefone(): void
    {
        $this->criarContexto();

        $this->withHeader('X-WhatsApp-Token', 'token-teste')
            ->postJson(route('api.whatsapp.criar-paciente'), [
                'nome' => 'João da Silva',
                'telefone' => '5511999998888',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('pacientes', [
            'nome' => 'João da Silva',
            'celular' => '5511999998888',
        ]);

        $this->withHeader('X-WhatsApp-Token', 'token-teste')
            ->postJson(route('api.whatsapp.criar-paciente'), [
                'nome' => 'João da Silva',
                'telefone' => '5511999998888',
            ])
            ->assertOk()
            ->assertJsonPath('paciente.nome', 'João da Silva');
    }

    public function test_agenda_consulta_confirmada(): void
    {
        $clinica = $this->criarContexto();
        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'Maria Souza',
            'celular' => '5511988887777',
            'status' => 'ativo',
        ]);
        $profissional = Professional::first();

        $this->withHeader('X-WhatsApp-Token', 'token-teste')
            ->postJson(route('api.whatsapp.agendamento'), [
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'dia' => now()->addDays(2)->format('Y-m-d'),
                'hora_inicio' => '09:00',
                'duracao' => 30,
                'tipo' => 'confirmado',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
        ]);
    }

    public function test_agenda_rejeita_conflito_de_horario(): void
    {
        $clinica = $this->criarContexto();
        $paciente = Paciente::create([
            'clinica_id' => $clinica->id,
            'nome' => 'Maria Souza',
            'celular' => '5511988887777',
            'status' => 'ativo',
        ]);
        $profissional = Professional::first();
        $inicio = now()->addDays(2)->setTime(9, 0);

        Appointment::create([
            'clinica_id' => $clinica->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'appointment_status_id' => AppointmentStatus::where('name', 'agendado')->value('id'),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
        ]);

        $this->withHeader('X-WhatsApp-Token', 'token-teste')
            ->postJson(route('api.whatsapp.agendamento'), [
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'dia' => $inicio->format('Y-m-d'),
                'hora_inicio' => '09:00',
                'duracao' => 30,
            ])
            ->assertStatus(409);
    }

    public function test_disponibilidade_lista_slots(): void
    {
        $this->criarContexto();

        $response = $this->withHeader('X-WhatsApp-Token', 'token-teste')
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1]))
            ->assertOk();

        $this->assertNotEmpty($response->json('dias'));
    }
}
