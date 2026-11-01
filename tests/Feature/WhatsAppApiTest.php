<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\Paciente;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Room;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\IntegrationAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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
     * A Evolution e o segredo do webhook ficam em endereço de teste: nenhum teste
     * pode alcançar a API de verdade, e um envio de mensagem para um número real
     * seria o pior resultado possível de uma suíte.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.webhook_secret' => 'segredo',
        ]);
    }

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
        ProfessionalSchedule::create([
            'professional_id' => Professional::first()->id,
            'weekday' => now()->addDay()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

        $response = $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1, 'duracao' => 30]))
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

        $rota = route('api.whatsapp.disponibilidade', ['dias' => 1, 'duracao' => 30]);

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
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1, 'duracao' => 30]))
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

    public function test_envia_mensagem_pela_evolution_e_registra_no_historico(): void
    {
        Http::fake([
            'https://evolution.test/*' => Http::response([
                'key' => ['id' => '3EB0OUTRA999'],
                'status' => 'PENDING',
            ], 201),
        ]);

        $clinica = $this->criarContexto(['messages.send']);

        $clinica->update([
            'evolution_instance' => 'clinica-teste',
            'evolution_token' => 'token-da-instancia',
        ]);

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.mensagem'), [
                'telefone' => '5511988887777',
                'texto' => 'Sua consulta está confirmada para amanhã às 09:00.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        // A Evolution autentica pelo header `apikey`, e o que ela aceita é o
        // token da INSTÂNCIA. Mandar a chave global daria autoridade sobre as
        // instâncias de todas as clínicas.
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://evolution.test/message/sendText/clinica-teste'
                && $request->hasHeader('apikey', 'token-da-instancia')
                && $request['number'] === '5511988887777';
        });

        // Registrar aqui, e não só pelo webhook, é o que mantém a resposta da
        // clínica no histórico mesmo com o n8n fora do ar.
        $mensagem = WhatsAppMessage::query()->firstOrFail();

        $this->assertTrue($mensagem->from_me);
        $this->assertSame('3EB0OUTRA999', $mensagem->message_key);
        $this->assertSame('5511988887777@s.whatsapp.net', $mensagem->remote_jid);
        $this->assertSame('Sua consulta está confirmada para amanhã às 09:00.', $mensagem->body);
    }

    public function test_clinica_sem_evolution_responde_503_e_nao_500(): void
    {
        Http::fake();

        $this->criarContexto(['messages.send']);

        // 503 e não 500: clínica sem instância é configuração pendente no painel,
        // não defeito. Um 500 aqui mandaria o n8n reentregar em loop e sugeriria
        // que o sistema está quebrado.
        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.mensagem'), [
                'telefone' => '5511988887777',
                'texto' => 'Olá',
            ])
            ->assertStatus(503);

        Http::assertNothingSent();
        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_token_sem_ability_de_envio_nao_manda_mensagem(): void
    {
        Http::fake();

        $clinica = $this->criarContexto(['appointments.create']);

        $clinica->update([
            'evolution_instance' => 'clinica-teste',
            'evolution_token' => 'token-da-instancia',
        ]);

        // Ability própria porque o efeito da rota não fica no banco: chega na
        // tela do WhatsApp de uma pessoa e não se desfaz apagando registro.
        // Quem pode agendar não pode, sozinho, falar como a clínica.
        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.mensagem'), [
                'telefone' => '5511988887777',
                'texto' => 'Olá',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_evolution_que_recusa_o_envio_responde_502(): void
    {
        Http::fake([
            'https://evolution.test/*' => Http::response(['error' => 'Invalid apikey'], 401),
        ]);

        $clinica = $this->criarContexto(['messages.send']);

        $clinica->update([
            'evolution_instance' => 'clinica-teste',
            'evolution_token' => 'token-errado',
        ]);

        // 502: o pedido chegou certo na Evolution e foi ela que recusou. Isso é
        // diferente de 500 (defeito aqui) e de 503 (configuração pendente).
        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.mensagem'), [
                'telefone' => '5511988887777',
                'texto' => 'Olá',
            ])
            ->assertStatus(502);

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_webhook_nao_duplica_a_mensagem_que_a_clinica_ja_enviou(): void
    {
        Http::fake([
            'https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0COMPARTILHADA']], 201),
        ]);

        $clinica = $this->criarContexto(['messages.send']);
        $clinica->update([
            'evolution_instance' => 'clinica-teste',
            'evolution_token' => 'token-da-instancia',
        ]);

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.mensagem'), [
                'telefone' => '5511988887777',
                'texto' => 'Olá',
            ])
            ->assertOk();

        // A rota de envio grava na hora, e o `messages.upsert` da mesma mensagem
        // chega depois com o mesmo `key.id`. O índice único absorve: sem ele o
        // paciente veria a resposta da clínica duas vezes na conversa.
        $this->postJson(route('api.evolution.webhook.handle'), [
            'event' => 'messages.upsert',
            'instance' => 'clinica-teste',
            'apikey' => 'token-da-instancia',
            'data' => [
                'key' => [
                    'remoteJid' => '5511988887777@s.whatsapp.net',
                    'fromMe' => true,
                    'id' => '3EB0COMPARTILHADA',
                ],
                'messageType' => 'conversation',
                'messageTimestamp' => 1772323200,
                'message' => ['conversation' => 'Olá'],
            ],
        ], ['X-Evolution-Secret' => 'segredo'])->assertOk();

        $this->assertSame(1, WhatsAppMessage::query()->count());
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

    /**
     * Agenda o profissional para um dia específico, como o painel cadastraria.
     */
    private function agendar(Professional $profissional, string $inicio = '08:00', string $fim = '12:00', ?string $dia = null): ProfessionalSchedule
    {
        return ProfessionalSchedule::create([
            'professional_id' => $profissional->id,
            'weekday' => Carbon::parse($dia ?? now()->addDay())->dayOfWeek,
            'start_time' => $inicio,
            'end_time' => $fim,
        ]);
    }

    public function test_procedimentos_lista_somente_os_ativos(): void
    {
        $this->criarContexto();

        Procedure::create(['codigo' => 'ATIVO', 'nome' => 'Consulta', 'duracao_minutos' => 30, 'is_active' => true]);
        Procedure::create(['codigo' => 'PARADO', 'nome' => 'Procedimento antigo', 'duracao_minutos' => 60, 'is_active' => false]);

        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.procedimentos'))
            ->assertOk()
            ->assertJsonCount(1, 'procedimentos')
            ->assertJsonPath('procedimentos.0.codigo', 'ATIVO')
            ->assertJsonPath('procedimentos.0.duracao_minutos', 30);
    }

    public function test_procedimento_precisa_da_ability_para_ser_listado(): void
    {
        $this->criarContexto(['appointments.view']);

        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.procedimentos'))
            ->assertForbidden();
    }

    public function test_disponibilidade_exige_duracao_ou_procedimento(): void
    {
        // Sem os dois, a API não teria como saber o tamanho da consulta e cairia
        // num 30 fixo — que é o horário errado para metade dos procedimentos.
        $this->criarContexto();

        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1]))
            ->assertStatus(422);
    }

    public function test_disponibilidade_usa_a_duracao_do_procedimento(): void
    {
        $this->criarContexto();
        $this->agendar(Professional::first(), '08:00', '12:00');

        $procedimento = Procedure::create([
            'codigo' => 'LONGO',
            'nome' => 'Tratamento longo',
            'duracao_minutos' => 120,
            'is_active' => true,
        ]);

        $response = $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', [
                'dias' => 1,
                'procedimento_id' => $procedimento->id,
            ]))
            ->assertOk()
            ->assertJsonPath('duracao', 120);

        // Turno de 4 h com consulta de 2 h: 08:00-10:00, 10:00-12:00. Sem a
        // duração real, o bot ofereceria 08:00, 08:30, 09:00... — encaixes que
        // dois pacientes não poderiam ocupar ao mesmo tempo.
        $this->assertSame(
            ['08:00', '10:00'],
            array_column($response->json('dias.0.horarios'), 'hora'),
        );
    }

    public function test_disponibilidade_nao_oferece_horario_que_ultrapassa_o_turno(): void
    {
        $this->criarContexto();
        $this->agendar(Professional::first(), '08:00', '18:00');

        // Turno fecha às 18:00 e a consulta dura 2 h: 17:00 terminaria 19:00,
        // fora do atendimento. O passo de 2 h já não gera 17:00, mas o teste
        // fixa a regra para o caso de o turno ser encurtado no meio do dia.
        $response = $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1, 'duracao' => 120]))
            ->assertOk();

        foreach ($response->json('dias.0.horarios') as $horario) {
            $this->assertLessThanOrEqual(
                '18:00',
                Carbon::parse('2000-01-01 '.$horario['hora'])->addMinutes(120)->format('H:i'),
                "O horário {$horario['hora']} termina depois do fim do turno.",
            );
        }
    }

    public function test_disponibilidade_nao_oferece_horario_que_corta_consulta_marcada(): void
    {
        $this->criarContexto();
        $profissional = Professional::first();
        $this->agendar($profissional, '08:00', '12:00');

        $inicio = now()->addDay()->setTime(9, 30);

        Appointment::create([
            'paciente_id' => Paciente::create(['nome' => 'Maria', 'celular' => '5511988887777', 'status' => 'ativo'])->id,
            'profissional_id' => $profissional->id,
            'appointment_status_id' => AppointmentStatus::where('name', 'agendado')->value('id'),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(60),
        ]);

        $horarios = array_column(
            $this->comoIntegracao()
                ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 1, 'duracao' => 30]))
                ->assertOk()
                ->json('dias.0.horarios'),
            'hora',
        );

        // occupied 09:30-10:30. Com passo de 30 min, 09:00-09:30 e 10:30-11:00
        // são livres de verdade: um encosta na consulta e o outro começa quando
        // ela termina. O que não pode aparecer é 09:30 e 10:00, que caem NO
        // MEIO dela — oferecer isso é o jeito de a clínica ganhar dois pacientes
        // no mesmo lugar.
        $this->assertNotContains('09:30', $horarios);
        $this->assertNotContains('10:00', $horarios);
        $this->assertContains('09:00', $horarios);
        $this->assertContains('10:30', $horarios);
        $this->assertContains('08:00', $horarios);
    }

    public function test_dia_sem_agenda_nao_entra_na_resposta(): void
    {
        $this->criarContexto();
        $this->agendar(Professional::first());

        $resposta = $this->comoIntegracao()
            ->getJson(route('api.whatsapp.disponibilidade', ['dias' => 7, 'duracao' => 30]))
            ->assertOk();

        // A agenda é só de amanhã, mas a janela vai até o sétimo dia: oferecer
        // um dia inteiro de horários que a clínica não atende é o que faz o
        // paciente escolher e o agendamento depois recusar.
        $this->assertCount(1, $resposta->json('dias'));
        $this->assertSame(now()->addDay()->toDateString(), $resposta->json('dias.0.data'));
    }

    public function test_conversa_nova_comeca_no_consentimento(): void
    {
        $this->criarContexto();

        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.conversa', ['remote_jid' => '5511999998888@s.whatsapp.net']))
            ->assertOk()
            ->assertJsonPath('conversa.etapa', 'consentimento')
            ->assertJsonPath('conversa.dados', null);
    }

    public function test_conversa_expirada_volta_para_o_consentimento(): void
    {
        // Sem esta checagem, uma conversa abandonada na etapa `horario` voltaria
        // no dia seguinte ainda esperando a escolha de um horário que já passou,
        // e o bot marcaria a consulta para um turno que não existe mais.
        $this->criarContexto();

        $jid = '5511999998888@s.whatsapp.net';

        $this->comoIntegracao()->putJson(route('api.whatsapp.conversa'), [
            'remote_jid' => $jid,
            'etapa' => 'horario',
            'dados' => ['profissional_id' => 1, 'hora' => '09:00'],
        ])->assertOk();

        WhatsAppConversation::query()->where('remote_jid', $jid)->update(['expires_at' => now()->subMinute()]);

        $this->comoIntegracao()
            ->getJson(route('api.whatsapp.conversa', ['remote_jid' => $jid]))
            ->assertOk()
            ->assertJsonPath('conversa.etapa', 'consentimento')
            ->assertJsonPath('conversa.dados', null);
    }

    public function test_conversa_atualiza_etapa_e_dados(): void
    {
        $this->criarContexto();

        $this->comoIntegracao()->putJson(route('api.whatsapp.conversa'), [
            'remote_jid' => '5511999998888@s.whatsapp.net',
            'etapa' => 'profissional',
            'dados' => ['profissional_id' => 7, 'procedimento_id' => 2],
        ])->assertOk();

        $this->assertTenantDatabaseHas('whatsapp_conversations', [
            'remote_jid' => '5511999998888@s.whatsapp.net',
            'etapa' => 'profissional',
        ]);
    }

    public function test_conversa_recusa_etapa_desconhecida(): void
    {
        // Etapa fora da lista viraria estado que o switch do n8n não conhece, e o
        // paciente receberia o menu como se a conversa tivesse começado de novo.
        $this->criarContexto();

        $this->comoIntegracao()->putJson(route('api.whatsapp.conversa'), [
            'remote_jid' => '5511999998888@s.whatsapp.net',
            'etapa' => 'inventada',
        ])->assertStatus(422);
    }

    public function test_conversa_recusa_lista_em_dados(): void
    {
        // A regra `array` do Laravel aceita lista, e `[1,2]` passaria por ela:
        // o n8n gravaria lista onde o próximo turno lê `dados.profissional_id`.
        $this->criarContexto();

        $this->comoIntegracao()->putJson(route('api.whatsapp.conversa'), [
            'remote_jid' => '5511999998888@s.whatsapp.net',
            'dados' => [1, 2],
        ])->assertStatus(422);
    }

    public function test_token_de_leitura_nao_altera_a_etapa_da_conversa(): void
    {
        // Ler o estado é `appointments.view`; mudar o estado é `appointments.create`.
        // Um token de leitura que reescrevesse a etapa mandaria o bot para um
        // estado que ninguém pediu.
        $this->criarContexto(['appointments.view']);

        $rota = route('api.whatsapp.conversa');

        $this->comoIntegracao()
            ->getJson($rota.'?remote_jid=5511999998888%40s.whatsapp.net')
            ->assertOk();

        $this->comoIntegracao()->putJson($rota, [
            'remote_jid' => '5511999998888@s.whatsapp.net',
            'etapa' => 'menu',
        ])->assertForbidden();
    }
}
