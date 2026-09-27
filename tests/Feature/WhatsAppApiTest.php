<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Room;
use App\Support\IntegrationAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class WhatsAppApiTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    /**
     * Token em claro da clínica. O banco guarda só o SHA-256, então o teste
     * precisa manter o valor original para apresentá-lo no header.
     */
    private string $token = 'token-teste';

    /**
     * Clínica com o banco tenant pronto, como o middleware espera.
     *
     * @param  list<string>|null  $abilities  `null` deixa o token sem restrição,
     *                                        que é o padrão de retrocompatibilidade
     */
    private function criarContexto(?array $abilities = null): Clinic
    {
        $clinica = $this->criarClinicaTeste('Clínica Teste');

        Professional::create([
            'nome' => 'Dra. Ana',
            'is_active' => true,
        ]);
        Room::create(['nome' => 'Sala 1', 'is_active' => true]);

        foreach (['agendado', 'confirmado', 'cancelado'] as $name) {
            AppointmentStatus::create(['name' => $name, 'label' => ucfirst($name)]);
        }

        IntegrationToken::create([
            'clinic_id' => $clinica->id,
            'name' => 'n8n',
            'token_hash' => IntegrationToken::hashToken($this->token),
            'abilities' => $abilities,
        ]);

        return $clinica;
    }

    private function comoIntegracao(): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    public function test_requisicao_sem_token_e_rejeitada(): void
    {
        $this->criarContexto();

        $this->getJson(route('api.whatsapp.info'))
            ->assertUnauthorized();
    }

    public function test_requisicao_com_token_invalido_e_rejeitada(): void
    {
        $this->criarContexto();

        $this->withHeader('Authorization', 'Bearer errado')
            ->getJson(route('api.whatsapp.info'))
            ->assertUnauthorized();
    }

    public function test_token_revogado_e_rejeitado(): void
    {
        $this->criarContexto();

        IntegrationToken::query()->first()->revoke();

        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.info'))
            ->assertUnauthorized();
    }

    public function test_info_retorna_status_da_agenda(): void
    {
        $this->criarContexto();

        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.info'))
            ->assertOk()
            ->assertJsonPath('clinica.nome', 'Clínica Teste')
            ->assertJsonCount(3, 'statuses');
    }

    public function test_cria_e_retorna_paciente_por_telefone(): void
    {
        $this->criarContexto();

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.criar-paciente'), [
                'nome' => 'João da Silva',
                'telefone' => '5511999998888',
            ])
            ->assertCreated();

        $this->assertTenantDatabaseHas('pacientes', [
            'nome' => 'João da Silva',
            'celular' => '5511999998888',
        ]);

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.criar-paciente'), [
                'nome' => 'João da Silva',
                'telefone' => '5511999998888',
            ])
            ->assertOk()
            ->assertJsonPath('paciente.nome', 'João da Silva');
    }

    public function test_agenda_consulta_confirmada(): void
    {
        $this->criarContexto();

        $paciente = Paciente::create([
            'nome' => 'Maria Souza',
            'celular' => '5511988887777',
            'status' => 'ativo',
        ]);
        $profissional = Professional::first();

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.agendamento'), [
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'dia' => now()->addDays(2)->format('Y-m-d'),
                'hora_inicio' => '09:00',
                'duracao' => 30,
                'tipo' => 'confirmado',
            ])
            ->assertCreated();

        $this->assertTenantDatabaseHas('appointments', [
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
        ]);
    }

    public function test_agenda_rejeita_conflito_de_horario(): void
    {
        $this->criarContexto();

        $paciente = Paciente::create([
            'nome' => 'Maria Souza',
            'celular' => '5511988887777',
            'status' => 'ativo',
        ]);
        $profissional = Professional::first();
        $inicio = now()->addDays(2)->setTime(9, 0);

        Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'appointment_status_id' => AppointmentStatus::where('name', 'agendado')->value('id'),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
        ]);

        $this->comoIntegracao()
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

        $response = $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1]))
            ->assertOk();

        $this->assertNotEmpty($response->json('dias'));
    }

    public function test_token_so_de_leitura_nao_cria_consulta(): void
    {
        $this->criarContexto(['appointments.view']);

        $paciente = Paciente::create(['nome' => 'Maria Souza', 'celular' => '5511988887777', 'status' => 'ativo']);
        $profissional = Professional::first();

        // Ler a agenda é permitido...
        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1]))
            ->assertOk();

        // ...criar consulta não é. Este é o ponto inteiro das abilities: sem
        // elas, um token de leitura também escrevia na agenda.
        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.agendamento'), [
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'dia' => now()->addDay()->format('Y-m-d'),
                'hora_inicio' => '09:00',
                'duracao' => 30,
            ])
            ->assertForbidden();

        $this->assertSame(0, Appointment::count());
    }

    public function test_token_so_de_leitura_nao_cria_paciente(): void
    {
        $this->criarContexto(['appointments.view']);

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.criar-paciente'), [
                'nome' => 'João da Silva',
                'telefone' => '5511999998888',
            ])
            ->assertForbidden();

        $this->assertSame(0, Paciente::count());
    }

    public function test_token_cannot_confirmar_consulta_sem_ability_de_atualizacao(): void
    {
        $this->criarContexto(['appointments.create']);

        $paciente = Paciente::create(['nome' => 'Maria Souza', 'celular' => '5511988887777', 'status' => 'ativo']);
        $profissional = Professional::first();

        // Criar é permitido, então a consulta existe...
        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.agendamento'), [
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'dia' => now()->addDay()->format('Y-m-d'),
                'hora_inicio' => '09:00',
                'duracao' => 30,
            ])
            ->assertCreated();

        // ...mas confirmar não, porque confirmar muda o estado da consulta.
        $consulta = Appointment::firstOrFail();

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.confirmar', ['consulta' => $consulta->id]))
            ->assertForbidden();
    }

    public function test_token_com_asterisco_passa_em_todo(): void
    {
        $this->criarContexto(['*']);

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.criar-paciente'), [
                'nome' => 'João da Silva',
                'telefone' => '5511999998888',
            ])
            ->assertCreated();
    }

    public function test_token_de_diagnostico_precisa_poder_chegar_em_info(): void
    {
        // `info` não tem ability: um token restrito ainda precisa confirmar que
        // está autenticado, senão o n8n não distingue "token errado" de
        // "token sem permissão" ao depurar.
        $this->criarContexto(['appointments.view']);

        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.info'))
            ->assertOk();
    }

    public function test_toda_rota_de_integracao_tem_ability_definida(): void
    {
        $rotas = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn (?string $name) => is_string($name) && str_starts_with($name, 'api.whatsapp.'));

        $this->assertNotEmpty($rotas, 'Nenhuma rota de integração encontrada para conferir.');

        // Rede de segurança do fail-closed: rota nova sem entrada no mapa
        // quebraria em produção, e este teste aponta qual é.
        $rotas->each(function (string $name): void {
            $this->assertArrayHasKey(
                $name,
                IntegrationAbilities::all(),
                "A rota [{$name}] não tem ability definida em IntegrationAbilities::MAP.",
            );
        });
    }
}
