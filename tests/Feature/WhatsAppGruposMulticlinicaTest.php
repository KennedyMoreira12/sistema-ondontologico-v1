<?php

namespace Tests\Feature;

use App\Models\Central\Clinic;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\N8nDispatcher;
use App\Support\WhatsAppJid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * A fronteira multi-clínica do WhatsApp: grupo não é paciente, cada clínica tem
 * o seu workflow e o seu token, e o dispatcher espera o turno inteiro do n8n.
 *
 * Nenhum teste daqui fala com a Evolution ou com o n8n de verdade: `Http::fake`
 * responde por eles e `preventStrayRequests` transforma qualquer chamada não
 * prevista em falha.
 */
class WhatsAppGruposMulticlinicaTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected const SEGREDO = 'segredo-do-webhook';

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();
        Http::preventStrayRequests();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.webhook_secret' => self::SEGREDO,
            'services.n8n.webhook_url' => 'https://n8n.test/webhook/global',
            'services.n8n.webhook_token' => null,
            'services.n8n.timeout' => 45,
            'services.atendimento_ia.enabled' => true,
            'services.atendimento_ia.api_key' => 'chave-de-teste',
            'services.atendimento_ia.base_url' => 'https://openrouter.test/api/v1',
            'services.atendimento_ia.model' => 'modelo-de-teste',
            'services.atendimento_ia.timeout' => 5,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Classificação de JID
    |--------------------------------------------------------------------------
    */

    public function test_grupo_nunca_e_tratado_como_telefone(): void
    {
        $this->assertTrue(WhatsAppJid::ehGrupo('120363000000000001@g.us'));
        $this->assertTrue(WhatsAppJid::ehGrupo('120363000000000001@G.US'));

        $this->assertFalse(WhatsAppJid::ehGrupo('5511999999999@s.whatsapp.net'));
        $this->assertFalse(WhatsAppJid::ehGrupo('123456789012345@lid'));

        $this->assertTrue(WhatsAppJid::ehTelefone('5511999999999'));
        $this->assertTrue(WhatsAppJid::ehTelefone('5511999999999@s.whatsapp.net'));
        $this->assertTrue(WhatsAppJid::ehTelefone('123456789012345@lid'));

        $this->assertFalse(WhatsAppJid::ehTelefone('120363000000000001@g.us'));
        $this->assertFalse(WhatsAppJid::ehTelefone('123'));
        $this->assertFalse(WhatsAppJid::ehTelefone('551198765432112345'));
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook: grupo bloqueado antes de gravar e de despachar
    |--------------------------------------------------------------------------
    */

    public function test_webhook_bloqueia_grupo_antes_de_gravar_e_de_encaminhar(): void
    {
        Http::fake();

        ['clinic' => $clinica, 'token' => $token] = $this->clinicaComWhatsApp('Clínica Malheiro', 'malheiro');

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem('malheiro', $token, [
            'data' => ['key' => ['remoteJid' => '120363000000000001@g.us']],
        ]), ['X-Evolution-Secret' => self::SEGREDO])
            ->assertOk()
            ->assertJson(['status' => 'ignored', 'motivo' => 'grupo']);

        $this->assertSame(0, WhatsAppMessage::query()->count());
        $this->assertSame(0, WhatsAppConversation::query()->count());
        Http::assertNothingSent();
    }

    public function test_webhook_encaminha_mensagem_privada_para_o_workflow_da_clinica(): void
    {
        Http::fake([
            'https://n8n.test/*' => Http::response(['ok' => true], 200),
        ]);

        ['clinic' => $clinica, 'token' => $token] = $this->clinicaComWhatsApp(
            'Clínica Malheiro',
            'malheiro',
            'https://n8n.test/webhook/LJAtendimento01/hook/atendimento-clinica',
        );

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem('malheiro', $token), [
            'X-Evolution-Secret' => self::SEGREDO,
        ])->assertOk()->assertJson(['status' => 'dispatched']);

        $mensagem = WhatsAppMessage::query()->firstOrFail();

        $this->assertNotNull($mensagem->forwarded_at);
        $this->assertSame('5511999999999', $mensagem->telefone());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://n8n.test/webhook/LJAtendimento01/hook/atendimento-clinica'
            && $request['clinic']['id'] === $clinica->id
            && $request['message']['telefone'] === '5511999999999');
    }

    public function test_contato_lid_preserva_o_telefone_real_e_nao_e_bloqueado(): void
    {
        Http::fake([
            'https://n8n.test/*' => Http::response(['ok' => true], 200),
        ]);

        ['clinic' => $clinica, 'token' => $token] = $this->clinicaComWhatsApp(
            'Clínica Malheiro',
            'malheiro',
            'https://n8n.test/webhook/LJAtendimento01/hook/atendimento-clinica',
        );

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem('malheiro', $token, [
            'data' => ['key' => [
                'remoteJid' => '123456789012345@lid',
                'remoteJidAlt' => '5511988887777@s.whatsapp.net',
            ]],
        ]), ['X-Evolution-Secret' => self::SEGREDO])
            ->assertOk()
            ->assertJson(['status' => 'dispatched']);

        $mensagem = WhatsAppMessage::query()->firstOrFail();

        $this->assertSame('5511988887777@s.whatsapp.net', $mensagem->remote_jid);
        $this->assertSame('5511988887777', $mensagem->telefone());

        Http::assertSent(fn (Request $request): bool => $request['clinic']['id'] === $clinica->id
            && $request['message']['telefone'] === '5511988887777');
    }

    /*
    |--------------------------------------------------------------------------
    | Fluxo interno: grupo não abre conversa
    |--------------------------------------------------------------------------
    */

    public function test_fluxo_com_ia_bloqueia_grupo_sem_abrir_conversa(): void
    {
        ['clinic' => $clinica, 'token' => $token] = $this->clinicaComWhatsApp('Clínica Malheiro', 'malheiro', comIA: true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '120363000000000001@g.us',
                'texto' => 'quanto custa a limpeza?',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'resposta' => '',
                'handled_by' => 'grupo',
            ]);

        $this->assertSame(0, WhatsAppConversation::query()->count());
        Http::assertNothingSent();
    }

    public function test_fluxo_deterministico_bloqueia_grupo_sem_abrir_conversa(): void
    {
        ['token' => $token] = $this->clinicaComWhatsApp('Clínica Malheiro', 'malheiro');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.whatsapp.fluxo'), [
                'remote_jid' => '120363000000000001@g.us',
                'texto' => 'oi',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'resposta' => '',
                'handled_by' => 'grupo',
            ]);

        $this->assertSame(0, WhatsAppConversation::query()->count());
        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Envio: grupo não chega à Evolution
    |--------------------------------------------------------------------------
    */

    public function test_envio_para_grupo_e_recusado_antes_da_evolution(): void
    {
        Http::fake();

        ['token' => $token] = $this->clinicaComWhatsApp('Clínica Malheiro', 'malheiro');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.whatsapp.mensagem'), [
                'telefone' => '120363000000000001@g.us',
                'texto' => 'resposta indevida',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'O destino não é um telefone individual do WhatsApp.']);

        Http::assertNothingSent();
    }

    public function test_envio_para_telefone_privado_continua_funcionando(): void
    {
        Http::fake([
            'https://evolution.test/*' => Http::response(['key' => ['id' => 'ABC123']], 201),
        ]);

        ['token' => $token] = $this->clinicaComWhatsApp('Clínica Malheiro', 'malheiro');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.whatsapp.mensagem'), [
                'telefone' => '5511988887777',
                'texto' => 'Olá! Como posso ajudar?',
            ])
            ->assertOk()
            ->assertJson(['status' => 'sent']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/malheiro')
            && $request['number'] === '5511988887777');
    }

    /*
    |--------------------------------------------------------------------------
    | Isolamento entre clínicas
    |--------------------------------------------------------------------------
    */

    public function test_cada_clinica_roteia_para_o_seu_proprio_workflow(): void
    {
        Http::fake([
            'https://n8n.test/*' => Http::response(['ok' => true], 200),
        ]);

        ['clinic' => $clinica1, 'token' => $token1] = $this->clinicaComWhatsApp(
            'Clínica Malheiro',
            'malheiro',
            'https://n8n.test/webhook/LJAtendimento01/hook/atendimento-clinica',
        );

        ['clinic' => $clinica5, 'token' => $token5] = $this->clinicaComWhatsApp(
            'Clínica Daiane',
            'daiane',
            'https://n8n.test/webhook/LJAtendimento05/hook/atendimento-clinica-05',
        );

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem('malheiro', $token1), [
            'X-Evolution-Secret' => self::SEGREDO,
        ])->assertOk()->assertJson(['status' => 'dispatched']);

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem('daiane', $token5), [
            'X-Evolution-Secret' => self::SEGREDO,
        ])->assertOk()->assertJson(['status' => 'dispatched']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://n8n.test/webhook/LJAtendimento01/hook/atendimento-clinica'
            && $request['clinic']['id'] === $clinica1->id);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://n8n.test/webhook/LJAtendimento05/hook/atendimento-clinica-05'
            && $request['clinic']['id'] === $clinica5->id);

        Http::assertSentCount(2);
    }

    public function test_token_de_uma_clinica_nao_autentica_a_instancia_de_outra(): void
    {
        Http::fake([
            'https://n8n.test/*' => Http::response(['ok' => true], 200),
        ]);

        ['token' => $tokenMalheiro] = $this->clinicaComWhatsApp('Clínica Malheiro', 'malheiro');
        ['token' => $tokenDaiane] = $this->clinicaComWhatsApp('Clínica Daiane', 'daiane');

        // Token da clínica 1 apresentado na instância da clínica 5: a mensagem
        // não pode entrar no banco da Daiane.
        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem('daiane', $tokenMalheiro), [
            'X-Evolution-Secret' => self::SEGREDO,
        ])->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::query()->count());
        Http::assertNothingSent();

        // Com o token da própria instância, a mesma entrega funciona.
        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem('daiane', $tokenDaiane), [
            'X-Evolution-Secret' => self::SEGREDO,
        ])->assertOk()->assertJson(['status' => 'dispatched']);

        $this->assertSame(1, WhatsAppMessage::query()->count());
        Http::assertSentCount(1);
    }

    /*
    |--------------------------------------------------------------------------
    | Timeout do dispatcher
    |--------------------------------------------------------------------------
    */

    public function test_dispatcher_tem_piso_de_45_segundos(): void
    {
        $this->assertSame(45, N8nDispatcher::TIMEOUT_PADRAO);

        $dispatcher = new class extends N8nDispatcher
        {
            public function timeoutUsado(): int
            {
                return $this->timeout();
            }
        };

        // Um `.env` antigo com 15 não pode reintroduzir o timeout curto.
        config(['services.n8n.timeout' => 15]);
        $this->assertSame(45, $dispatcher->timeoutUsado());

        // Acima do piso, o valor configurado continua valendo.
        config(['services.n8n.timeout' => 90]);
        $this->assertSame(90, $dispatcher->timeoutUsado());
    }

    /*
    |--------------------------------------------------------------------------
    | Apoio
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{clinic: Clinic, token: string}
     */
    protected function clinicaComWhatsApp(
        string $nome,
        string $instancia,
        ?string $urlN8n = null,
        bool $comIA = false,
    ): array {
        $dados = $this->criarClinica($nome, strtolower($instancia).'@teste.test');
        $clinica = $dados['clinic'];

        $clinica->update([
            'evolution_instance' => $instancia,
            'evolution_token' => $dados['token'],
            'n8n_webhook_url' => $urlN8n,
        ]);

        if ($comIA) {
            $clinica->latestSubscription->plan->features()->create([
                'feature' => 'ai_agent',
                'limits' => [],
            ]);
        }

        return ['clinic' => $clinica, 'token' => $dados['token']];
    }

    /**
     * Payload mínimo de `messages.upsert`, com o token da instância no corpo.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payloadDeMensagem(string $instancia, string $token, array $overrides = []): array
    {
        $padrao = [
            'event' => 'messages.upsert',
            'instance' => $instancia,
            'apikey' => $token,
            'data' => [
                'key' => [
                    'remoteJid' => '5511999999999@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => '3EB0'.substr(md5(uniqid('', true)), 0, 10),
                ],
                'pushName' => 'Maria Souza',
                'messageType' => 'conversation',
                'messageTimestamp' => 1772323200,
                'message' => ['conversation' => 'Bom dia, quero marcar uma consulta'],
            ],
        ];

        $data = array_replace_recursive($padrao['data'], $overrides['data'] ?? []);
        unset($overrides['data']);

        return array_replace($padrao, $overrides, ['data' => $data]);
    }
}
