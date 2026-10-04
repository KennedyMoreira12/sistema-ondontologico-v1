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
     * Clínica com o banco tenant pronto, o plano que libera a API e o token, como
     * o middleware espera.
     *
     * @param  list<string>|null  $abilities  `null` dá acesso completo, que é o
     *                                        que o painel nunca gera: ele exige
     *                                        escolha explícita. Quem restringe
     *                                        token é o teste.
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

        $this->assinarPlanoComWhatsapp($clinica);

        IntegrationToken::create([
            'clinic_id' => $clinica->id,
            'name' => 'n8n',
            'token_hash' => IntegrationToken::hashToken($this->token),
            'abilities' => $abilities ?? [IntegrationAbilities::WILDCARD],
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

    public function test_token_sem_abilities_nao_acessa_nada(): void
    {
        // Token antigo, criado antes de existir a lista de permissões, ou token
        // gravado direto no banco. O comportamento precisa ser negar: o antigo
        // `hasAbility()` devolvia verdadeiro para lista vazia, o que fazia token
        // sem permissão nenhuma passar em tudo.
        $this->criarContexto([]);

        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1]))
            ->assertForbidden();
    }

    public function test_clinica_sem_assinatura_ativa_nao_acessa_a_api(): void
    {
        // Nem token perfeito entra sem assinatura em vigor. `feature` vem como
        // "subscription", e não como null, para o n8n saber que a resposta é
        // "venda", e não "falta o recurso X neste plano".
        $clinica = $this->criarContexto();
        $clinica->latestSubscription->delete();

        $response = $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1]))
            ->assertForbidden()
            ->assertJson(['feature' => 'subscription']);

        $this->assertStringContainsString('assinatura não está ativa', $response->json('message'));
    }

    public function test_plano_sem_o_recurso_whatsapp_nao_acessa_a_api(): void
    {
        // Assinatura ativa, plano que não inclui o WhatsApp. Aqui a resposta tem
        // que dizer QUAL recurso faltou, e não apenas "403".
        $clinica = $this->criarContexto();

        $clinica->latestSubscription->plan->features()->delete();

        $clinica->load(['latestSubscription.plan.features']);

        $response = $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1]))
            ->assertForbidden()
            ->assertJson(['feature' => 'whatsapp']);

        $this->assertStringContainsString('WhatsApp', $response->json('message'));
    }

    public function test_reenvio_com_a_mesma_chave_nao_duplica_consulta(): void
    {
        $this->criarContexto();

        $paciente = Paciente::create(['nome' => 'Maria Souza', 'celular' => '5511988887777', 'status' => 'ativo']);
        $profissional = Professional::first();

        $payload = [
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'dia' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '09:00',
            'duracao' => 30,
            'idempotency_key' => 'n8n-msg-4711',
        ];

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.agendamento'), $payload)
            ->assertCreated();

        // O n8n reenvia enquanto não recebe resposta; a segunda entrega tem que
        // devolver a MESMA consulta, não criar outra nem dar 409 por horário.
        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.agendamento'), $payload)
            ->assertOk()
            ->assertJsonPath('agendamento.id', 1);

        $this->assertSame(1, Appointment::count());
    }

    public function test_chave_de_idempotencia_diferente_no_mesmo_horario_conflita(): void
    {
        // O lock é por profissional e dia, então duas requisições com chaves
        // diferentes no mesmo horário não passam: a segunda é 409, e o
        // profissional fica com uma consulta só.
        $this->criarContexto();

        $paciente = Paciente::create(['nome' => 'Maria Souza', 'celular' => '5511988887777', 'status' => 'ativo']);
        $profissional = Professional::first();

        $payload = [
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'dia' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '09:00',
            'duracao' => 30,
        ];

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.agendamento'), $payload + ['idempotency_key' => 'a'])
            ->assertCreated();

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.agendamento'), $payload + ['idempotency_key' => 'b'])
            ->assertStatus(409);

        $this->assertSame(1, Appointment::count());
    }

    public function test_escrita_e_limitada_por_token(): void
    {
        // O balde de escrita existe para o caso em que o n8n entra em loop: 20
        // escritas por minuto por token. O limite é por TOKEN e não por IP porque
        // todas as clínicas saem do mesmo endereço — por IP, a mais barulhenta
        // derrubaria as outras.
        $this->criarContexto();

        $rota = route('api.whatsapp.criar-paciente');

        for ($i = 0; $i < 20; $i++) {
            $this->comoIntegracao()
                ->postJson($rota, ['nome' => 'Paciente '.$i, 'telefone' => '55119999000'.$i])
                ->assertCreated();
        }

        $this->comoIntegracao()
            ->postJson($rota, ['nome' => 'Estourou', 'telefone' => '55119999999'])
            ->assertStatus(429);
    }

    public function test_leitura_tem_limite_mais_alto_que_escrita(): void
    {
        // 25 leituras passam todas, enquanto 21 escritas já estourariam. É o que
        // impede a troca "um limite só, mais frouxo" de passar no teste anterior e
        // travar o bot de atendimento no meio do dia.
        $this->criarContexto();

        $rota = route('api.whatsapp.disponibilidade', ['dias' => 1]);

        for ($i = 0; $i < 25; $i++) {
            $this->comoIntegracao()->getJson($rota)->assertOk();
        }
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
