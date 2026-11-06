<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppOutboxMessage;
use App\Models\Central\Clinic;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppOutboxMessage;
use App\Services\Tenant\TenantRunner;
use App\Services\WhatsApp\ClinicFeatures;
use App\Services\WhatsApp\Outbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * A outbox é a garantia de que "decidir responder" não se perde no caminho.
 *
 * O que este arquivo protege, em ordem de importância:
 *
 * 1. com a flag DESLIGADA nada muda — o caminho antigo continua sendo o padrão;
 * 2. com a flag LIGADA a mensagem é persistida antes do envio, o job é
 *    idempotente e o worker encontra o banco da clínica sem sessão;
 * 3. falha que melhora sozinha tem retry; falha definitiva vai para a DLQ;
 * 4. timeout NÃO é reenviado automaticamente, porque pode duplicar a mensagem
 *    no celular do paciente;
 * 5. status de entrega/leitura atualiza o histórico sem reenviar ao n8n.
 */
class WhatsAppOutboxTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private const SEGREDO = 'segredo-do-webhook';

    private const TOKEN_INSTANCIA = 'token-da-instancia';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.webhook_secret' => self::SEGREDO,
        ]);
    }

    /**
     * @return array{clinic: Clinic, user: mixed, token: string}
     */
    private function clinicaComWhatsApp(bool $outboxLigada = false): array
    {
        $contexto = $this->criarClinica();

        $contexto['clinic']->update([
            'evolution_instance' => 'clinica-teste',
            'evolution_token' => self::TOKEN_INSTANCIA,
        ]);

        if ($outboxLigada) {
            app(ClinicFeatures::class)->set($contexto['clinic'], 'whatsapp_outbox', true);
        }

        return $contexto;
    }

    private function enviar(string $token, array $dados = []): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.whatsapp.mensagem'), $dados + [
                'telefone' => '5511988887777',
                'texto' => 'Sua consulta está confirmada.',
            ]);
    }

    public function test_flag_desligada_preserva_o_envio_direto_e_nao_cria_outbox(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0DIRETA']], 201)]);

        $contexto = $this->clinicaComWhatsApp();

        $this->enviar($contexto['token'])
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        $this->assertTenantDatabaseCount('whatsapp_outbox', 0);
        $this->assertTenantDatabaseCount('whatsapp_messages', 1);
    }

    public function test_flag_ligada_persiste_na_outbox_e_registra_historico_com_correlacao(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0OUTBOX']], 201)]);

        $contexto = $this->clinicaComWhatsApp(outboxLigada: true);

        $resposta = $this->withHeader('Authorization', 'Bearer '.$contexto['token'])
            ->withHeader('X-Correlation-Id', 'corr-123')
            ->postJson(route('api.whatsapp.mensagem'), [
                'telefone' => '5511988887777',
                'texto' => 'Olá pela outbox',
            ]);

        $resposta->assertOk()
            ->assertJsonPath('status', 'sent')
            ->assertJsonPath('provider_message_id', '3EB0OUTBOX')
            ->assertHeader('X-Correlation-Id', 'corr-123');

        $outbox = WhatsAppOutboxMessage::query()->firstOrFail();

        $this->assertSame(WhatsAppOutboxMessage::SENT, $outbox->status);
        $this->assertSame('3EB0OUTBOX', $outbox->provider_message_id);
        $this->assertSame(1, $outbox->attempts);
        $this->assertSame('corr-123', $outbox->correlation_id);

        $mensagem = WhatsAppMessage::query()->firstOrFail();

        $this->assertSame('corr-123', $mensagem->correlation_id);
        $this->assertSame('sent', $mensagem->delivery_status);
        $this->assertSame('3EB0OUTBOX', $mensagem->message_key);
    }

    public function test_idempotency_key_nao_envia_a_mesma_mensagem_duas_vezes(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0UNICA']], 201)]);

        $contexto = $this->clinicaComWhatsApp(outboxLigada: true);

        $dados = [
            'telefone' => '5511988887777',
            'texto' => 'Confirmação',
            'idempotency_key' => 'execucao-42',
        ];

        $this->enviar($contexto['token'], $dados)->assertOk()->assertJsonPath('status', 'sent');
        $this->enviar($contexto['token'], $dados)->assertOk()->assertJsonPath('status', 'sent');

        $this->assertTenantDatabaseCount('whatsapp_outbox', 1);
        $this->assertTenantDatabaseCount('whatsapp_messages', 1);
        Http::assertSentCount(1);
    }

    public function test_erro_5xx_agenda_retry_e_a_recuperacao_reenvia(): void
    {
        Http::fakeSequence('https://evolution.test/*')
            ->push(['error' => 'instável'], 500)
            ->push(['key' => ['id' => '3EB0RETRY']], 201);

        $contexto = $this->clinicaComWhatsApp(outboxLigada: true);

        $this->enviar($contexto['token'])->assertStatus(202)->assertJsonPath('status', 'queued');

        $outbox = WhatsAppOutboxMessage::query()->firstOrFail();

        $this->assertSame(WhatsAppOutboxMessage::PENDING, $outbox->status);
        $this->assertSame('evolution_recusou', $outbox->last_error_code);
        $this->assertSame(1, $outbox->attempts);
        $this->assertTrue($outbox->available_at->isFuture());

        $this->travel(10)->minutes();

        $this->artisan('whatsapp:outbox:recover')->assertSuccessful();

        $outbox->refresh();

        $this->assertSame(WhatsAppOutboxMessage::SENT, $outbox->status);
        $this->assertSame('3EB0RETRY', $outbox->provider_message_id);
        Http::assertSentCount(2);
    }

    public function test_recusa_definitiva_vai_para_dlq_e_nao_e_reenviada_pela_recuperacao(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['error' => 'Invalid apikey'], 401)]);

        $contexto = $this->clinicaComWhatsApp(outboxLigada: true);

        $this->enviar($contexto['token'])->assertStatus(502)->assertJsonPath('status', 'failed');

        $outbox = WhatsAppOutboxMessage::query()->firstOrFail();

        $this->assertSame(WhatsAppOutboxMessage::DEAD, $outbox->status);
        $this->assertSame('evolution_recusou', $outbox->last_error_code);

        $this->artisan('whatsapp:outbox:recover')->assertSuccessful();

        $this->assertSame(WhatsAppOutboxMessage::DEAD, $outbox->refresh()->status);
        Http::assertSentCount(1);
        $this->assertTenantDatabaseCount('whatsapp_messages', 0);
    }

    public function test_clinica_sem_integracao_responde_503_e_marca_dlq(): void
    {
        Http::fake();

        // Sem `evolution_instance`/`evolution_token`: a outbox registra a
        // mensagem, mas não há como enviar. 503, porque isso se resolve no
        // painel e não é defeito do sistema.
        $contexto = $this->criarClinica();

        app(ClinicFeatures::class)->set($contexto['clinic'], 'whatsapp_outbox', true);

        $this->enviar($contexto['token'])->assertStatus(503);

        $outbox = WhatsAppOutboxMessage::query()->firstOrFail();

        $this->assertSame(WhatsAppOutboxMessage::DEAD, $outbox->status);
        $this->assertSame('sem_integracao', $outbox->last_error_code);
        Http::assertNothingSent();
    }

    public function test_timeout_e_entrega_incerta_e_nao_e_reenviado_automaticamente(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('cURL error 28: Operation timed out after 30001 milliseconds');
        });

        $contexto = $this->clinicaComWhatsApp(outboxLigada: true);

        $this->enviar($contexto['token'])->assertStatus(502);

        $outbox = WhatsAppOutboxMessage::query()->firstOrFail();

        $this->assertSame(WhatsAppOutboxMessage::DEAD, $outbox->status);
        $this->assertSame('incerto', $outbox->last_error_code);

        $this->travel(30)->minutes();
        $this->artisan('whatsapp:outbox:recover')->assertSuccessful();

        // Nada é reenviado automaticamente: a linha continua na DLQ e a
        // tentativa não avança. O replay manual é quem assume o risco.
        $this->assertSame(WhatsAppOutboxMessage::DEAD, $outbox->refresh()->status);
        $this->assertSame(1, $outbox->attempts);
    }

    public function test_worker_sem_tenant_processa_a_clinica_dona_da_linha(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0WORKER']], 201)]);

        $a = $this->criarClinica('Clínica A', 'a@clinica.test');
        $b = $this->criarClinica('Clínica B', 'b@clinica.test');

        $b['clinic']->update([
            'evolution_instance' => 'clinica-b',
            'evolution_token' => self::TOKEN_INSTANCIA,
        ]);

        $this->conectar($b['clinic']);

        $outbox = WhatsAppOutboxMessage::create([
            'message_key' => 'local-teste-worker',
            'remote_jid' => '5511988887777@s.whatsapp.net',
            'telefone' => '5511988887777',
            'kind' => 'text',
            'body' => 'Mensagem da clínica B',
            'source' => 'teste',
            'status' => WhatsAppOutboxMessage::PENDING,
            'attempts' => 0,
            'max_attempts' => 5,
            'available_at' => now(),
        ]);

        // O processo está conectado na clínica A quando o job da B roda. É o
        // cenário do worker de fila, que não tem sessão nem tenant.
        $this->conectar($a['clinic']);

        (new SendWhatsAppOutboxMessage((int) $b['clinic']->id, (int) $outbox->id))
            ->handle(app(TenantRunner::class), app(Outbox::class));

        $this->conectar($b['clinic']);

        $this->assertSame(WhatsAppOutboxMessage::SENT, WhatsAppOutboxMessage::query()->findOrFail($outbox->id)->status);
        $this->assertSame(1, WhatsAppMessage::query()->count());

        $this->conectar($a['clinic']);

        $this->assertSame(0, WhatsAppMessage::query()->count());
        $this->assertSame(0, WhatsAppOutboxMessage::query()->count());
    }

    public function test_webhook_de_status_atualiza_entrega_e_leitura_sem_rebaixar(): void
    {
        $contexto = $this->clinicaComWhatsApp();

        $mensagem = WhatsAppMessage::create([
            'message_key' => '3EB0STATUS',
            'from_me' => true,
            'event' => 'MESSAGES_UPSERT',
            'message_type' => 'conversation',
            'remote_jid' => '5511988887777@s.whatsapp.net',
            'body' => 'Olá',
            'delivery_status' => 'pending',
        ]);

        $this->postJson(route('api.evolution.webhook.handle'), [
            'event' => 'messages.update',
            'instance' => 'clinica-teste',
            'apikey' => self::TOKEN_INSTANCIA,
            'data' => [
                'key' => [
                    'remoteJid' => '5511988887777@s.whatsapp.net',
                    'fromMe' => true,
                    'id' => '3EB0STATUS',
                ],
                'update' => ['status' => 'READ'],
                'messageTimestamp' => 1772323200,
            ],
        ], ['X-Evolution-Secret' => self::SEGREDO])
            ->assertOk()
            ->assertJsonPath('status', 'status_updated')
            ->assertJsonPath('updated', 1);

        $mensagem->refresh();

        $this->assertSame('read', $mensagem->delivery_status);
        $this->assertNotNull($mensagem->read_at);

        // Evento antigo reentregue: não desfaz uma leitura confirmada.
        $this->postJson(route('api.evolution.webhook.handle'), [
            'event' => 'messages.update',
            'instance' => 'clinica-teste',
            'apikey' => self::TOKEN_INSTANCIA,
            'data' => [
                'key' => [
                    'remoteJid' => '5511988887777@s.whatsapp.net',
                    'fromMe' => true,
                    'id' => '3EB0STATUS',
                ],
                'update' => ['status' => 'SERVER_ACK'],
            ],
        ], ['X-Evolution-Secret' => self::SEGREDO])->assertOk();

        $this->assertSame('read', $mensagem->refresh()->delivery_status);
    }

    public function test_recuperacao_devolve_linha_orfa_de_processing_para_a_fila(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0ORFA']], 201)]);

        $contexto = $this->clinicaComWhatsApp();

        $outbox = WhatsAppOutboxMessage::create([
            'message_key' => 'local-orfa',
            'remote_jid' => '5511988887777@s.whatsapp.net',
            'telefone' => '5511988887777',
            'kind' => 'text',
            'body' => 'Ficou presa',
            'status' => WhatsAppOutboxMessage::PROCESSING,
            'attempts' => 1,
            'max_attempts' => 5,
            'locked_at' => now()->subHour(),
            'available_at' => now()->subHour(),
        ]);

        $this->artisan('whatsapp:outbox:recover')->assertSuccessful();

        $outbox->refresh();

        $this->assertSame(WhatsAppOutboxMessage::SENT, $outbox->status);
        $this->assertSame('3EB0ORFA', $outbox->provider_message_id);
    }

    public function test_replay_reprocessa_uma_linha_da_dlq(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0REPLAY']], 201)]);

        $contexto = $this->clinicaComWhatsApp();

        $outbox = WhatsAppOutboxMessage::create([
            'message_key' => 'local-dlq',
            'remote_jid' => '5511988887777@s.whatsapp.net',
            'telefone' => '5511988887777',
            'kind' => 'text',
            'body' => 'Estava na DLQ',
            'status' => WhatsAppOutboxMessage::DEAD,
            'attempts' => 5,
            'max_attempts' => 5,
            'last_error' => 'Timeout',
            'last_error_code' => 'incerto',
        ]);

        $this->artisan('whatsapp:outbox:replay', [
            'clinic' => $contexto['clinic']->id,
            'outbox' => $outbox->id,
        ])->assertSuccessful();

        $this->assertSame(WhatsAppOutboxMessage::SENT, $outbox->refresh()->status);
        $this->assertNull($outbox->last_error);
    }

    public function test_saude_mostra_integracao_outbox_e_flags_sem_chamar_a_evolution(): void
    {
        Http::fake();

        $contexto = $this->clinicaComWhatsApp(outboxLigada: true);

        WhatsAppOutboxMessage::create([
            'message_key' => 'local-saude',
            'remote_jid' => '5511988887777@s.whatsapp.net',
            'telefone' => '5511988887777',
            'kind' => 'text',
            'body' => 'Pendente',
            'status' => WhatsAppOutboxMessage::PENDING,
            'available_at' => now(),
            'max_attempts' => 5,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$contexto['token'])
            ->getJson(route('api.whatsapp.saude'))
            ->assertOk()
            ->assertJsonPath('whatsapp.integration_ready', true)
            ->assertJsonPath('whatsapp.outbox.pending', 1)
            ->assertJsonPath('whatsapp.connection', null)
            ->assertJsonPath('flags.whatsapp_outbox', true);

        Http::assertNothingSent();
    }
}
