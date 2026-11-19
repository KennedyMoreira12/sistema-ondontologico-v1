<?php

namespace Tests\Feature;

use App\Models\Central\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Os sete estados do monitoramento e as transições que importam.
 *
 * A regra central: falha ao CONSULTAR não é desconexão. O histórico da última
 * conexão permanece, mas o estado atual deixa de ser `open` e o atendimento é
 * bloqueado até uma confirmação válida.
 */
class MonitoramentoWhatsAppClinicasTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.evolution.url', 'http://evolution.test');
        Config::set('services.evolution.admin_key', 'chave-administrativa');
        Config::set('services.evolution.webhook_secret', 'segredo-do-webhook');
        Config::set('services.evolution.webhook_url', 'https://app.test/api/evolution/webhook');
        Config::set('services.n8n.webhook_url', 'https://n8n.test/webhook/global');
    }

    public function test_monitoramento_registra_conexao_confirmada_e_historico(): void
    {
        $this->fakeEvolution('open');
        $clinica = $this->clinicaMonitorada();

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])
            ->assertSuccessful();

        $clinica->refresh();

        $this->assertSame('conectado', $clinica->whatsapp_status);
        $this->assertSame('open', $clinica->whatsapp_current_state);
        $this->assertNotNull($clinica->whatsapp_connected_at);
        $this->assertNotNull($clinica->whatsapp_last_connected_at);
        $this->assertNotNull($clinica->whatsapp_current_state_at);
        $this->assertTrue($clinica->whatsappPronto());
    }

    public function test_falha_transitoria_marca_erro_de_comunicacao_sem_desconectar(): void
    {
        $this->fakeEvolutionErro(500);
        $clinica = $this->clinicaMonitorada([
            'whatsapp_status' => 'conectado',
            'whatsapp_current_state' => 'open',
            'whatsapp_connected_at' => now()->subHour(),
            'whatsapp_last_connected_at' => null,
        ]);
        $anterior = $clinica->whatsapp_connected_at->toDateTimeString();

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])
            ->assertFailed();

        $clinica->refresh();

        $this->assertSame('erro_comunicacao', $clinica->whatsapp_status);
        $this->assertSame('erro_comunicacao', $clinica->whatsapp_current_state);
        $this->assertSame($anterior, $clinica->whatsapp_connected_at->toDateTimeString());
        $this->assertSame($anterior, $clinica->whatsapp_last_connected_at->toDateTimeString());
        $this->assertFalse($clinica->whatsappPronto());
        $this->assertNotNull($clinica->whatsapp_last_error);
    }

    public function test_erro_de_comunicacao_recupera_quando_a_evolution_volta(): void
    {
        $respostas = [
            Http::response([], 500),
            Http::response(['instance' => ['state' => 'open']], 200),
        ];

        Http::fake([
            'evolution.test/instance/connectionState/*' => function () use (&$respostas) {
                return array_shift($respostas);
            },
        ]);

        $clinica = $this->clinicaMonitorada([
            'whatsapp_status' => 'conectado',
            'whatsapp_current_state' => 'open',
            'whatsapp_connected_at' => now()->subHours(2),
            'whatsapp_last_connected_at' => now()->subHours(2),
        ]);
        $historico = $clinica->whatsapp_last_connected_at->toDateTimeString();

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])->assertFailed();

        $this->assertFalse($clinica->fresh()->whatsappPronto());
        $this->assertSame($historico, $clinica->fresh()->whatsapp_last_connected_at->toDateTimeString());

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])->assertSuccessful();

        $clinica->refresh();

        $this->assertSame('open', $clinica->whatsapp_current_state);
        $this->assertNull($clinica->whatsapp_last_error);
        $this->assertTrue($clinica->whatsappPronto());
    }

    public function test_instancia_inexistente_tem_estado_proprio(): void
    {
        $this->fakeEvolutionErro(404);
        $clinica = $this->clinicaMonitorada([
            'whatsapp_status' => 'conectado',
            'whatsapp_current_state' => 'open',
            'whatsapp_connected_at' => now()->subDay(),
        ]);
        $anterior = $clinica->whatsapp_connected_at->toDateTimeString();

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])->assertFailed();

        $clinica->refresh();

        $this->assertSame('instancia_inexistente', $clinica->whatsapp_status);
        $this->assertSame('instancia_inexistente', $clinica->whatsapp_current_state);
        $this->assertSame($anterior, $clinica->whatsapp_connected_at->toDateTimeString());
        $this->assertFalse($clinica->whatsappPronto());
    }

    public function test_desconexao_confirmada_limpa_a_prova_atual_mas_preserva_o_historico(): void
    {
        $this->fakeEvolution('close');
        $clinica = $this->clinicaMonitorada([
            'whatsapp_status' => 'conectado',
            'whatsapp_current_state' => 'open',
            'whatsapp_connected_at' => now()->subHour(),
            'whatsapp_last_connected_at' => now()->subHour(),
        ]);

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])->assertSuccessful();

        $clinica->refresh();

        $this->assertSame('desconectado', $clinica->whatsapp_status);
        $this->assertSame('close', $clinica->whatsapp_current_state);
        $this->assertNull($clinica->whatsapp_connected_at);
        $this->assertNotNull($clinica->whatsapp_last_connected_at);
        $this->assertFalse($clinica->whatsappPronto());
    }

    public function test_resposta_sem_estado_resulta_em_desconhecido(): void
    {
        Http::fake(['evolution.test/instance/connectionState/*' => Http::response([], 200)]);
        $clinica = $this->clinicaMonitorada([
            'whatsapp_status' => 'conectado',
            'whatsapp_current_state' => 'open',
            'whatsapp_connected_at' => now()->subHour(),
        ]);

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])->assertSuccessful();

        $clinica->refresh();

        $this->assertSame('desconhecido', $clinica->whatsapp_status);
        $this->assertSame('desconhecido', $clinica->whatsapp_current_state);
        $this->assertNull($clinica->whatsapp_connected_at);
        $this->assertFalse($clinica->whatsappPronto());
    }

    public function test_monitoramento_sem_integracao_nao_afirma_conexao(): void
    {
        $this->freezeTime();
        Http::fake();
        ['clinic' => $clinica] = $this->criarClinica('Clínica Sem WhatsApp', 'sem@teste.test', comPlano: false);

        $this->artisan('whatsapp:clinicas:monitorar')->assertSuccessful();
        $confirmadoEm = $clinica->fresh()->whatsapp_current_state_at?->toDateTimeString();

        $this->artisan('whatsapp:clinicas:monitorar')->assertSuccessful();

        $this->assertSame('sem_integracao', $clinica->fresh()->whatsapp_current_state);
        $this->assertSame($confirmadoEm, $clinica->fresh()->whatsapp_current_state_at?->toDateTimeString());
        Http::assertNothingSent();
    }

    public function test_monitoramento_e_idempotente(): void
    {
        $this->freezeTime();
        $this->fakeEvolution('open');
        $clinica = $this->clinicaMonitorada();

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])->assertSuccessful();
        $primeira = $clinica->fresh()->whatsapp_connected_at->toDateTimeString();
        $historico = $clinica->fresh()->whatsapp_last_connected_at->toDateTimeString();

        $this->artisan('whatsapp:clinicas:monitorar', ['--clinic' => $clinica->id])->assertSuccessful();

        $this->assertSame($primeira, $clinica->fresh()->whatsapp_connected_at->toDateTimeString());
        $this->assertSame($historico, $clinica->fresh()->whatsapp_last_connected_at->toDateTimeString());
        $this->assertSame('conectado', $clinica->fresh()->whatsapp_status);
    }

    public function test_mensagem_webhook_confirma_conexao_apos_falha_transitoria(): void
    {
        Config::set('services.evolution.url', 'http://evolution.test');

        $clinica = $this->clinicaMonitorada([
            'whatsapp_status' => 'erro_comunicacao',
            'whatsapp_current_state' => 'erro_comunicacao',
            'whatsapp_connected_at' => now()->subHour(),
            'whatsapp_last_connected_at' => now()->subHour(),
        ]);

        Http::fake(['n8n.test/*' => Http::response(['ok' => true], 200)]);

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadMensagem($clinica), [
            'X-Evolution-Secret' => 'segredo-do-webhook',
        ])->assertOk()->assertJson(['status' => 'dispatched']);

        $clinica->refresh();

        $this->assertSame('open', $clinica->whatsapp_current_state);
        $this->assertSame('conectado', $clinica->whatsapp_status);
        $this->assertTrue($clinica->whatsappPronto());
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function clinicaMonitorada(array $atributos = []): Clinic
    {
        ['clinic' => $clinica] = $this->criarClinica('Clínica Aurora', 'aurora@teste.test');

        $clinica->forceFill(array_merge([
            'evolution_instance' => 'clinica-aurora',
            'evolution_token' => 'token-aurora',
            'whatsapp_provisioned_at' => now(),
            'whatsapp_webhook_configured_at' => now(),
            'n8n_webhook_url' => 'https://n8n.test/webhook/clinica-aurora',
        ], $atributos))->save();

        return $clinica->fresh();
    }

    protected function fakeEvolution(string $estado): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => $estado]], 200),
        ]);
    }

    protected function fakeEvolutionErro(int $status): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response(['message' => 'falha'], $status),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadMensagem(Clinic $clinica): array
    {
        return [
            'event' => 'messages.upsert',
            'instance' => (string) $clinica->evolution_instance,
            'apikey' => (string) $clinica->evolution_token,
            'data' => [
                'key' => [
                    'remoteJid' => '5511999999999@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => '3EB0MONITOR',
                ],
                'pushName' => 'Maria Souza',
                'messageType' => 'conversation',
                'messageTimestamp' => 1772323200,
                'message' => ['conversation' => 'Bom dia'],
            ],
        ];
    }
}
