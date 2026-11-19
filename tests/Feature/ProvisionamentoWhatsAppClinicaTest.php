<?php

namespace Tests\Feature;

use App\Models\Central\AdminUser;
use App\Models\Central\Clinic;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\ClinicWhatsAppProvisioner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Provisionamento do WhatsApp de uma clínica nova.
 *
 * O que este arquivo protege é o contrato do onboarding automático: instância
 * exclusiva, token individual cifrado, webhook com o token DAQUELA clínica,
 * destino de n8n resolvido e — o mais importante — atendimento bloqueado
 * enquanto webhook, conexão e roteamento não estiverem de pé.
 *
 * Nada aqui toca a Evolution de verdade: todas as chamadas são `Http::fake`, e
 * nenhuma mensagem é enviada.
 */
class ProvisionamentoWhatsAppClinicaTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected const SEGREDO = 'segredo-do-webhook';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.evolution.url', 'http://evolution.test');
        Config::set('services.evolution.admin_key', 'chave-administrativa');
        Config::set('services.evolution.webhook_secret', self::SEGREDO);
        Config::set('services.evolution.webhook_url', 'https://app.test/api/evolution/webhook');
        Config::set('services.n8n.clinic_webhook_template', 'https://n8n.test/webhook/{instance}/hook/atendimento');
        Config::set('services.n8n.webhook_url', 'https://n8n.test/webhook/global');
    }

    /*
    |--------------------------------------------------------------------------
    | Provisionamento
    |--------------------------------------------------------------------------
    */

    public function test_provisiona_instancia_exclusiva_com_token_cifrado_webhook_e_roteamento(): void
    {
        $this->fakeEvolution(token: 'token-da-aurora');

        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        $resultado = app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $clinica->refresh();

        $this->assertTrue($resultado['ok']);
        $this->assertTrue($resultado['criada']);
        $this->assertSame('clinica-'.$clinica->id.'-clinica-aurora', $clinica->evolution_instance);

        // O token em claro só existe na memória; no banco, o cast `encrypted`
        // grava outra coisa. Um dump do banco central não entrega a credencial.
        $this->assertSame('token-da-aurora', $clinica->evolution_token);
        $this->assertNotSame(
            'token-da-aurora',
            DB::table('clinics')->where('id', $clinica->id)->value('evolution_token'),
        );

        $this->assertNotNull($clinica->whatsapp_provisioned_at);
        $this->assertNotNull($clinica->whatsapp_webhook_configured_at);
        $this->assertSame('desconectado', $clinica->whatsapp_status);

        // Destino do n8n resolvido pelo template, sem ninguém digitar a URL.
        $this->assertSame(
            'https://n8n.test/webhook/clinica-'.$clinica->id.'-clinica-aurora/hook/atendimento',
            $clinica->n8n_webhook_url,
        );

        // Webhook configurado com o token desta clínica, o segredo da instalação
        // e os oito eventos — inclusive CONNECTION_UPDATE, que é o que alimenta
        // o monitoramento.
        Http::assertSent(function (Request $request) use ($clinica): bool {
            if (! str_contains($request->url(), '/webhook/set/'.$clinica->evolution_instance)) {
                return false;
            }

            $corpo = $request->data();

            return $request->hasHeader('apikey', 'chave-administrativa')
                && ($corpo['webhook']['url'] ?? null) === 'https://app.test/api/evolution/webhook'
                && ($corpo['webhook']['enabled'] ?? null) === true
                && ($corpo['webhook']['headers']['apikey'] ?? null) === 'token-da-aurora'
                && ($corpo['webhook']['headers']['X-Evolution-Secret'] ?? null) === self::SEGREDO
                && count($corpo['webhook']['events'] ?? []) === 8
                && in_array('CONNECTION_UPDATE', $corpo['webhook']['events'], true);
        });

        // Desconectada: provisionada, mas ainda sem atender.
        $this->assertFalse($clinica->whatsappPronto());
        $this->assertContains('conexao', $resultado['pendencias']);
    }

    public function test_clinica_so_fica_pronta_quando_a_conexao_abre(): void
    {
        $this->fakeEvolution(token: 'token-da-aurora', estado: 'open');

        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $clinica->refresh();

        $this->assertSame('conectado', $clinica->whatsapp_status);
        $this->assertNotNull($clinica->whatsapp_connected_at);
        $this->assertTrue($clinica->whatsappPronto());
    }

    public function test_reprovisionar_nao_renomeia_nem_recria_instancia_existente(): void
    {
        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        $clinica->update([
            'evolution_instance' => 'clinica-aurora',
            'evolution_token' => 'token-antigo',
        ]);

        // A Evolution conhece a instância e devolve o token dela.
        Http::fake([
            'evolution.test/instance/fetchInstances' => Http::response([
                [
                    'id' => 'uuid-aurora',
                    'name' => 'clinica-aurora',
                    'connectionStatus' => 'close',
                    'token' => 'token-atual',
                ],
            ], 200),
            'evolution.test/webhook/set/*' => Http::response(['webhook' => ['enabled' => true]], 200),
            'evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'close']], 200),
        ]);

        $resultado = app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $clinica->refresh();

        $this->assertTrue($resultado['ok']);
        $this->assertFalse($resultado['criada']);
        $this->assertSame('clinica-aurora', $clinica->evolution_instance);
        $this->assertSame('token-atual', $clinica->evolution_token);

        // Instância adotada: nunca é recriada.
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/create'));
    }

    public function test_nomes_de_instancia_nao_colidem_entre_clinicas_homonimas(): void
    {
        $this->fakeEvolution();

        $primeira = $this->criarClinica('Clínica Sorriso', 'um@teste.test')['clinic'];
        $segunda = $this->criarClinica('Clínica Sorriso', 'dois@teste.test')['clinic'];

        $provisioner = app(ClinicWhatsAppProvisioner::class);

        $provisioner->provision($primeira);
        $provisioner->provision($segunda);

        $this->assertSame('clinica-'.$primeira->id.'-clinica-sorriso', $primeira->fresh()->evolution_instance);
        $this->assertSame('clinica-'.$segunda->id.'-clinica-sorriso', $segunda->fresh()->evolution_instance);
        $this->assertNotSame($primeira->fresh()->evolution_instance, $segunda->fresh()->evolution_instance);
    }

    public function test_plano_sem_whatsapp_nao_cria_instancia(): void
    {
        Http::fake();

        $clinica = $this->criarClinica('Clínica Sem WhatsApp', 'sem@teste.test', comPlano: false)['clinic'];

        $resultado = app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $this->assertFalse($resultado['ok']);
        $this->assertSame(['plano_sem_whatsapp'], $resultado['pendencias']);
        $this->assertSame('nao_contratado', $clinica->fresh()->whatsapp_status);
        $this->assertNull($clinica->fresh()->evolution_instance);

        Http::assertNothingSent();
    }

    public function test_falha_da_evolution_registra_erro_sem_apagar_a_clinica(): void
    {
        Http::fake([
            'evolution.test/instance/fetchInstances' => Http::response([], 200),
            'evolution.test/instance/create' => Http::response(['message' => 'instabilidade interna'], 500),
        ]);

        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        $resultado = app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $clinica->refresh();

        $this->assertFalse($resultado['ok']);
        $this->assertSame('erro', $clinica->whatsapp_status);
        $this->assertNotNull($clinica->whatsapp_last_error);
        $this->assertStringContainsString('HTTP 500', (string) $clinica->whatsapp_last_error);
        $this->assertNull($clinica->whatsapp_provisioned_at);
        $this->assertNotNull(Clinic::query()->find($clinica->id), 'A clínica não pode sumir por causa da Evolution.');
    }

    public function test_sem_segredo_de_webhook_o_provisionamento_falha_em_vez_de_abrir_a_rota(): void
    {
        Config::set('services.evolution.webhook_secret', '');

        $this->fakeEvolution(token: 'token-da-aurora');

        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        $resultado = app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('EVOLUTION_WEBHOOK_SECRET', (string) $resultado['erro']);
        $this->assertSame('erro', $clinica->fresh()->whatsapp_status);
        $this->assertNull($clinica->fresh()->whatsapp_webhook_configured_at);

        // Instância criada, webhook NÃO: melhor uma instância sem webhook do que
        // um webhook aberto para qualquer um.
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/webhook/set/'));
    }

    /*
    |--------------------------------------------------------------------------
    | Isolamento entre clínicas
    |--------------------------------------------------------------------------
    */

    public function test_cada_clinica_recebe_instancia_token_e_destino_proprios(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/instance/fetchInstances')) {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/instance/create')) {
                $nome = (string) ($request->data()['instanceName'] ?? '');

                return Http::response([
                    'instance' => ['instanceName' => $nome, 'instanceId' => 'uuid-'.$nome, 'status' => 'close'],
                    'hash' => ['apikey' => 'token-'.$nome],
                ], 201);
            }

            if (str_contains($request->url(), '/webhook/set/')) {
                return Http::response(['webhook' => ['enabled' => true]], 200);
            }

            return Http::response(['instance' => ['state' => 'close']], 200);
        });

        $aurora = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];
        $borealis = $this->criarClinica('Clínica Borealis', 'borealis@teste.test')['clinic'];

        $provisioner = app(ClinicWhatsAppProvisioner::class);

        $provisioner->provision($aurora);
        $provisioner->provision($borealis);

        $aurora->refresh();
        $borealis->refresh();

        $this->assertNotSame($aurora->evolution_instance, $borealis->evolution_instance);
        $this->assertNotSame($aurora->evolution_token, $borealis->evolution_token);
        $this->assertNotSame($aurora->n8n_webhook_url, $borealis->n8n_webhook_url);

        // O webhook da Aurora carrega o token da Aurora; o da Borealis, o dela.
        foreach ([$aurora, $borealis] as $clinica) {
            Http::assertSent(function (Request $request) use ($clinica): bool {
                if (! str_contains($request->url(), '/webhook/set/'.$clinica->evolution_instance)) {
                    return false;
                }

                return ($request->data()['webhook']['headers']['apikey'] ?? null) === $clinica->evolution_token;
            });
        }

        // Nenhuma requisição de configuração leva o token da outra clínica.
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/webhook/set/')
            && ($request->data()['webhook']['headers']['apikey'] ?? null) === $borealis->evolution_token
            && str_contains($request->url(), $aurora->evolution_instance));
    }

    /*
    |--------------------------------------------------------------------------
    | Ativação e monitoramento
    |--------------------------------------------------------------------------
    */

    public function test_webhook_nao_entrega_ao_n8n_antes_da_ativacao(): void
    {
        Http::fake(['n8n.test/*' => Http::response([], 200)]);

        $clinica = $this->clinicaComInstancia('clinica-pendente', 'token-pendente');

        // Provisionada, mas sem webhook, sem conexão e sem roteamento.
        $clinica->update(['whatsapp_provisioned_at' => now()]);

        $this->postJson(
            route('api.evolution.webhook.handle'),
            $this->payloadDeMensagem('clinica-pendente', 'token-pendente'),
            ['X-Evolution-Secret' => self::SEGREDO],
        )
            ->assertOk()
            ->assertJson(['status' => 'ignored', 'motivo' => 'whatsapp_nao_ativado']);

        Http::assertNothingSent();
        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_clinica_ativada_entrega_a_mensagem_no_n8n_dela(): void
    {
        $this->fakeEvolution(token: 'token-da-aurora', estado: 'open');

        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $clinica->refresh();
        $this->assertTrue($clinica->whatsappPronto());

        Http::fake(['n8n.test/*' => Http::response(['ok' => true], 200)]);

        $this->postJson(
            route('api.evolution.webhook.handle'),
            $this->payloadDeMensagem((string) $clinica->evolution_instance, 'token-da-aurora'),
            ['X-Evolution-Secret' => self::SEGREDO],
        )->assertOk()->assertJson(['status' => 'dispatched']);

        Http::assertSent(fn (Request $request): bool => $request->url() === $clinica->n8n_webhook_url);
    }

    public function test_falha_de_roteamento_marca_erro_e_o_sucesso_seguinte_restaura_o_status(): void
    {
        /**
         * O erro de n8n é passageiro: se a próxima entrega funciona, o painel
         * não pode continuar mostrando a clínica como "erro" até a varredura
         * seguinte — o operador precisa ver o estado real.
         */
        $this->fakeEvolution(token: 'token-da-aurora', estado: 'open');

        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $clinica->refresh();
        $this->assertSame('conectado', $clinica->whatsapp_status);

        $respostas = [
            Http::response(['erro' => 'fora do ar'], 500),
            Http::response(['ok' => true], 200),
        ];

        Http::fake(['n8n.test/*' => function () use (&$respostas) {
            return array_shift($respostas);
        }]);

        $this->postJson(
            route('api.evolution.webhook.handle'),
            $this->payloadDeMensagem((string) $clinica->evolution_instance, 'token-da-aurora', '3EB0FALHA'),
            ['X-Evolution-Secret' => self::SEGREDO],
        )->assertStatus(503);

        $clinica->refresh();
        $this->assertSame('erro', $clinica->whatsapp_status);
        $this->assertNotNull($clinica->whatsapp_last_error);

        $this->postJson(
            route('api.evolution.webhook.handle'),
            $this->payloadDeMensagem((string) $clinica->evolution_instance, 'token-da-aurora', '3EB0OK'),
            ['X-Evolution-Secret' => self::SEGREDO],
        )->assertOk()->assertJson(['status' => 'dispatched']);

        $clinica->refresh();
        $this->assertNull($clinica->whatsapp_last_error);
        $this->assertSame('conectado', $clinica->whatsapp_status);
    }

    public function test_evento_de_conexao_atualiza_o_status_da_clinica(): void
    {
        Http::fake();

        $clinica = $this->clinicaComInstancia('clinica-aurora', 'token-da-aurora');

        $this->postJson(route('api.evolution.webhook.handle'), [
            'event' => 'connection.update',
            'instance' => 'clinica-aurora',
            'apikey' => 'token-da-aurora',
            'data' => ['instance' => 'clinica-aurora', 'state' => 'open'],
        ], ['X-Evolution-Secret' => self::SEGREDO])->assertOk();

        $clinica->refresh();

        $this->assertSame('conectado', $clinica->whatsapp_status);
        $this->assertNotNull($clinica->whatsapp_connected_at);
    }

    public function test_evento_de_queda_limpa_a_prova_de_conexao(): void
    {
        Http::fake();

        $clinica = $this->clinicaComInstancia('clinica-aurora', 'token-da-aurora');

        $clinica->update([
            'whatsapp_provisioned_at' => now(),
            'whatsapp_webhook_configured_at' => now(),
            'whatsapp_connected_at' => now(),
            'whatsapp_status' => 'conectado',
            'n8n_webhook_url' => 'https://n8n.test/webhook/clinica-aurora/hook/atendimento',
        ]);

        $this->postJson(route('api.evolution.webhook.handle'), [
            'event' => 'connection.update',
            'instance' => 'clinica-aurora',
            'apikey' => 'token-da-aurora',
            'data' => ['instance' => 'clinica-aurora', 'state' => 'close'],
        ], ['X-Evolution-Secret' => self::SEGREDO])->assertOk();

        $clinica->refresh();

        $this->assertSame('desconectado', $clinica->whatsapp_status);
        $this->assertNull($clinica->whatsapp_connected_at);
        $this->assertFalse($clinica->whatsappPronto());
    }

    public function test_comando_de_monitoramento_atualiza_estado_sem_gerar_qr(): void
    {
        $this->fakeEvolution(token: 'token-da-aurora', estado: 'open');

        $clinica = $this->clinicaComInstancia('clinica-aurora', 'token-da-aurora');

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])
            ->assertSuccessful();

        $this->assertSame('conectado', $clinica->fresh()->whatsapp_status);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/connect/'));
    }

    /*
    |--------------------------------------------------------------------------
    | Painel
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_provisiona_pelo_painel_e_suporte_nao(): void
    {
        $this->fakeEvolution(token: 'token-da-aurora');

        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        $suporte = AdminUser::factory()->support()->create();

        $this->actingAs($suporte, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.provisionar', $clinica))
            ->assertForbidden();

        Http::assertNothingSent();

        $admin = AdminUser::factory()->create();

        $this->actingAs($admin, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.provisionar', $clinica))
            ->assertOk()
            ->assertJson(['ok' => true, 'provisionado' => true]);

        $this->assertNotNull($clinica->fresh()->evolution_instance);
    }

    public function test_template_n8n_com_placeholder_desconhecido_bloqueia_o_provisionamento(): void
    {
        Config::set('services.n8n.clinic_webhook_template', 'https://n8n.test/webhook/{instanceId}/hook/atendimento');
        $this->fakeEvolution(token: 'token-da-aurora');

        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        $resultado = app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $this->assertFalse($resultado['ok']);
        $this->assertSame('erro', $resultado['status']);
        $this->assertNull($clinica->fresh()->n8n_webhook_url);
        $this->assertStringContainsString(
            'placeholder não suportado',
            (string) $clinica->fresh()->whatsapp_last_error
        );
        $this->assertFalse($clinica->fresh()->whatsappPronto());
    }

    public function test_template_n8n_que_nao_gera_url_http_bloqueia_o_provisionamento(): void
    {
        Config::set('services.n8n.clinic_webhook_template', 'ftp://n8n.test/{instance}');
        $this->fakeEvolution(token: 'token-da-aurora');

        $clinica = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];

        $resultado = app(ClinicWhatsAppProvisioner::class)->provision($clinica);

        $this->assertFalse($resultado['ok']);
        $this->assertNull($clinica->fresh()->n8n_webhook_url);
        $this->assertStringContainsString(
            'URL http(s) válida',
            (string) $clinica->fresh()->whatsapp_last_error
        );
    }

    public function test_reprovisionamento_nao_adota_instancia_vinculada_a_outra_clinica(): void
    {
        $aurora = $this->criarClinica('Clínica Aurora', 'aurora@teste.test')['clinic'];
        $borealis = $this->criarClinica('Clínica Borealis', 'borealis@teste.test')['clinic'];

        // Estado legado corrompido: as duas apontam para a mesma instância. O
        // índice único é removido só neste banco de teste para montar o cenário.
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropUnique(['evolution_instance']);
        });

        $aurora->update([
            'evolution_instance' => 'clinica-compartilhada',
            'evolution_token' => 'token-da-aurora',
        ]);
        $borealis->update([
            'evolution_instance' => 'clinica-compartilhada',
            'evolution_token' => 'token-da-borealis',
        ]);

        Http::fake([
            'evolution.test/instance/fetchInstances' => Http::response([
                [
                    'id' => 'uuid-compartilhado',
                    'name' => 'clinica-compartilhada',
                    'connectionStatus' => 'close',
                    'token' => 'token-da-aurora',
                ],
            ], 200),
        ]);

        $resultado = app(ClinicWhatsAppProvisioner::class)->provision($borealis);

        $this->assertFalse($resultado['ok']);
        $this->assertSame('erro', $resultado['status']);
        $this->assertSame('token-da-borealis', $borealis->fresh()->evolution_token);
        $this->assertStringContainsString(
            'outra clínica',
            (string) $borealis->fresh()->whatsapp_last_error
        );
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/webhook/set/'));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Evolution falsa com os endpoints que o provisionamento usa.
     */
    protected function fakeEvolution(string $token = 'token-da-instancia', string $estado = 'close'): void
    {
        Http::fake([
            'evolution.test/instance/fetchInstances' => Http::response([], 200),
            'evolution.test/instance/create' => Http::response([
                'instance' => ['instanceName' => 'criada', 'instanceId' => 'uuid-1', 'status' => 'close'],
                'hash' => ['apikey' => $token],
            ], 201),
            'evolution.test/webhook/set/*' => Http::response(['webhook' => ['enabled' => true]], 200),
            'evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => $estado]], 200),
        ]);
    }

    protected function clinicaComInstancia(string $instancia, string $token): Clinic
    {
        $clinica = $this->criarClinica('Clínica '.$instancia, $instancia.'@teste.test')['clinic'];

        $clinica->update([
            'evolution_instance' => $instancia,
            'evolution_token' => $token,
        ]);

        return $clinica->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadDeMensagem(string $instancia, string $token, string $id = '3EB0ABC123'): array
    {
        return [
            'event' => 'messages.upsert',
            'instance' => $instancia,
            'apikey' => $token,
            'data' => [
                'key' => [
                    'remoteJid' => '5511999999999@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => $id,
                ],
                'pushName' => 'Maria Souza',
                'messageType' => 'conversation',
                'messageTimestamp' => 1772323200,
                'message' => ['conversation' => 'Bom dia, quero marcar uma consulta'],
            ],
        ];
    }
}
