<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateEvolutionWebhook;
use App\Models\Central\Clinic;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * O webhook da Evolution é a única porta PÚBLICA que escreve no banco de uma
 * clínica. O que este arquivo garante é que ela só abre para a Evolution de
 * verdade, e que o que entra por ela entra uma vez só.
 *
 * A Evolution não assina o webhook, então a defesa são duas checagens
 * independentes: o segredo no header e o token da instância no corpo. Qualquer
 * uma das duas falhando tem de fechar a porta — inclusive quando o segredo não
 * está configurado, que é o estado em que a rota fica fechada por padrão.
 *
 * @see AuthenticateEvolutionWebhook
 */
class EvolutionWebhookTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected const SEGREDO = 'segredo-do-webhook';

    protected const TOKEN_INSTANCIA = 'token-da-instancia-malheiro';

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();

        config([
            'services.evolution.webhook_secret' => self::SEGREDO,
            'services.n8n.webhook_url' => 'https://n8n.test/webhook/evolucao',
            'services.n8n.webhook_token' => 'segredo-do-n8n',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Autenticação
    |--------------------------------------------------------------------------
    */

    public function test_recusa_webhook_sem_o_segredo_configurado(): void
    {
        // Estado de instalação nova: a variável não existe. A rota precisa ficar
        // fechada, e não aberta por acidente — aceitar aqui daria a qualquer um
        // que achasse a URL o direito de criar paciente e agendamento.
        config(['services.evolution.webhook_secret' => null]);

        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]))->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_recusa_webhook_com_segredo_errado(): void
    {
        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]), ['X-Evolution-Secret' => 'chute'])->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_recusa_webhook_sem_o_header_de_segredo(): void
    {
        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]))->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_segredo_so_nao_basta_com_token_de_outra_instancia(): void
    {
        // O segredo é o mesmo para todas as clínicas. Se ele sozinho abrisse a
        // porta, quemasse qualquer uma escreveria mensagem na fila da outra.
        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => 'token-de-outra-instancia',
        ]), ['X-Evolution-Secret' => self::SEGREDO])->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_recusa_instancia_sem_clinica(): void
    {
        $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => 'instancia-que-nao-existe',
            'apikey' => self::TOKEN_INSTANCIA,
        ]), ['X-Evolution-Secret' => self::SEGREDO])->assertNotFound();

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_recusa_clinica_sem_acesso_liberado(): void
    {
        $clinica = $this->clinicaComEvolucao();
        $clinica->update(['status' => 'suspended', 'is_active' => false]);

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]), ['X-Evolution-Secret' => self::SEGREDO])->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Entrada válida
    |--------------------------------------------------------------------------
    */

    public function test_grava_a_mensagem_e_entrega_ao_n8n(): void
    {
        Http::fake([
            'https://n8n.test/*' => Http::response(['ok' => true], 200),
        ]);

        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]), ['X-Evolution-Secret' => self::SEGREDO])
            ->assertOk()
            ->assertJsonPath('status', 'dispatched');

        $mensagem = WhatsAppMessage::query()->firstOrFail();

        $this->assertSame('3EB0ABC123', $mensagem->message_key);
        $this->assertSame('conversation', $mensagem->message_type);
        $this->assertSame('5511999999999@s.whatsapp.net', $mensagem->remote_jid);
        $this->assertSame('Maria Souza', $mensagem->push_name);
        $this->assertSame('Bom dia, quero marcar uma consulta', $mensagem->body);
        $this->assertFalse($mensagem->from_me);
        $this->assertNotNull($mensagem->sent_at);
        $this->assertNotNull($mensagem->forwarded_at);

        // O Laravel entrega o evento já resolvido: o n8n não precisa manter mapa
        // de instância para clínica, que é o que desatualiza em silêncio.
        Http::assertSent(function (Request $request) use ($clinica) {
            return $request->url() === 'https://n8n.test/webhook/evolucao'
                && $request['clinic']['id'] === $clinica->id
                && $request['clinic']['evolution_instance'] === $clinica->evolution_instance
                && $request['message']['text'] === 'Bom dia, quero marcar uma consulta'
                && $request['message']['telefone'] === '5511999999999'
                && $request['event']['nome'] === 'MESSAGES_UPSERT';
        });

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-N8n-Token', 'segredo-do-n8n'));
    }

    public function test_converte_segundos_do_whatsapp_sem_cair_em_1970(): void
    {
        Http::fake(['https://n8n.test/*' => Http::response([], 200)]);

        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]), ['X-Evolution-Secret' => self::SEGREDO])->assertOk();

        // `messageTimestamp` é unix em SEGUNDOS. Passado cru para o cast datetime
        // vira microssegundos e grava 1970, com o histórico fora de ordem.
        $this->assertSame(
            '2026-03-01',
            WhatsAppMessage::query()->firstOrFail()->sent_at->toDateString()
        );
    }

    public function test_telefone_de_contato_novo_vem_do_remote_jid_alt(): void
    {
        Http::fake(['https://n8n.test/*' => Http::response([], 200)]);

        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
            'data' => [
                'key' => [
                    'remoteJid' => '987654321@lid',
                    'remoteJidAlt' => '5511988887777@s.whatsapp.net',
                ],
            ],
        ]), ['X-Evolution-Secret' => self::SEGREDO])->assertOk();

        // `@lid` não é telefone: é id opaco que nunca casa com pacientes.celular.
        // Sem esta precedência, todo paciente novo viraria "não encontrado".
        $this->assertSame(
            '5511988887777@s.whatsapp.net',
            WhatsAppMessage::query()->firstOrFail()->remote_jid
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Dedupe e reentrega
    |--------------------------------------------------------------------------
    */

    public function test_reentrega_nao_atende_o_paciente_de_novo(): void
    {
        Http::fake(['https://n8n.test/*' => Http::response([], 200)]);

        $clinica = $this->clinicaComEvolucao();
        $payload = $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]);

        $this->postJson(route('api.evolution.webhook.handle'), $payload, $this->comSegredo())
            ->assertOk()
            ->assertJsonPath('status', 'dispatched');

        $this->postJson(route('api.evolution.webhook.handle'), $payload, $this->comSegredo())
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, WhatsAppMessage::query()->count());

        // O n8n é chamado UMA vez. A reentrega da Evolution é normal e não pode
        // virar duas respostas do bot na tela do paciente.
        Http::assertSentCount(1);
    }

    public function test_reentrega_tenta_de_novo_quando_o_n8n_esta_fora_do_ar(): void
    {
        // Uma sequência só: a Evolution começa recebendo 503 e volta a receber
        // 200, que é o cenário de n8n reiniciando com a fila parada.
        Http::fakeSequence()
            ->pushStatus(503)
            ->pushStatus(200);

        $clinica = $this->clinicaComEvolucao();
        $payload = $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]);

        // 5xx do n8n é recuperável, então a Evolution tem de reentregar: é o
        // retry dela que faz a fila andar quando o n8n reinicia.
        $this->postJson(route('api.evolution.webhook.handle'), $payload, $this->comSegredo())
            ->assertStatus(503)
            ->assertJsonPath('status', 'stored');

        $mensagem = WhatsAppMessage::query()->firstOrFail();
        $this->assertNull($mensagem->forwarded_at);
        $this->assertNotNull($mensagem->forward_error);

        // A reentrega precisa achar a mensagem e DESPACHAR, não responder 200 do
        // dedupe. Sem isso o 503 acima era mentira: dez retries, zero entregas.
        $this->postJson(route('api.evolution.webhook.handle'), $payload, $this->comSegredo())
            ->assertOk()
            ->assertJsonPath('status', 'dispatched');

        $this->assertNotNull($mensagem->fresh()->forwarded_at);
    }

    public function test_falha_que_nao_melhora_nao_derruba_a_reentrega_da_evolution(): void
    {
        // `N8N_WEBHOOK_URL` vazia ou 4xx do n8n não melhoram com reentrega.
        // Responder 503 gastaria os dez retries da Evolution atrás de algo que
        // só o operador pode arrumar.
        config(['services.n8n.webhook_url' => '']);

        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]), $this->comSegredo())
            ->assertOk()
            ->assertJsonPath('status', 'stored');

        $this->assertSame(1, WhatsAppMessage::query()->count());
        $this->assertNotNull(WhatsAppMessage::query()->firstOrFail()->forward_error);
    }

    public function test_mensagem_enviada_pela_clinica_nao_conflita_com_a_entrada(): void
    {
        Http::fake(['https://n8n.test/*' => Http::response([], 200)]);

        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
            'data' => ['key' => ['fromMe' => true]],
        ]), $this->comSegredo())->assertOk();

        $this->assertTrue(WhatsAppMessage::query()->firstOrFail()->from_me);

        // Mesma chave, sentido oposto: são duas mensagens diferentes e as duas
        // precisam caber. Por isso a unicidade é (message_key, from_me).
        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
        ]), $this->comSegredo())->assertOk();

        $this->assertSame(2, WhatsAppMessage::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Eventos que não são mensagem
    |--------------------------------------------------------------------------
    */

    public function test_evento_de_estado_da_conexao_nao_vira_mensagem(): void
    {
        Http::fake();

        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), [
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
            'event' => 'connection.update',
            'data' => ['connection' => 'close'],
        ], $this->comSegredo())
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        // Descarregar sem registro tiraria do operador a única pista de por que a
        // clínica parou de receber mensagem.
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $mensagem) => str_contains($mensagem, 'estado da conexão'));

        $this->assertSame(0, WhatsAppMessage::query()->count());
        Http::assertNothingSent();
    }

    public function test_evento_desconhecido_e_ignorado_sem_erro(): void
    {
        Http::fake();

        $clinica = $this->clinicaComEvolucao();

        $this->postJson(route('api.evolution.webhook.handle'), [
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
            'event' => 'PRESENCE_UPDATE',
            'data' => [],
        ], $this->comSegredo())
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_payload_sem_nome_de_evento_nao_vira_500(): void
    {
        Http::fake();

        $clinica = $this->clinicaComEvolucao();

        // 500 aqui faria a Evolution reentregar em loop um payload que ela vai
        // mandar igual para sempre.
        $this->postJson(route('api.evolution.webhook.handle'), [
            'instance' => $clinica->evolution_instance,
            'apikey' => self::TOKEN_INSTANCIA,
            'data' => ['key' => ['id' => '3EB0ABC123']],
        ], $this->comSegredo())
            ->assertOk()
            ->assertJsonPath('status', 'ignored');
    }

    /*
    |--------------------------------------------------------------------------
    | Isolamento
    |--------------------------------------------------------------------------
    */

    public function test_mensagem_vai_para_o_banco_da_clinica_da_instancia(): void
    {
        Http::fake(['https://n8n.test/*' => Http::response([], 200)]);

        $malheiro = $this->clinicaComEvolucao();

        $outra = $this->criarClinica('Clínica Outra', 'outra@teste.test');
        $outra['clinic']->update([
            'evolution_instance' => 'clinica-outra',
            'evolution_token' => 'token-da-outra',
        ]);

        $this->postJson(route('api.evolution.webhook.handle'), $this->payloadDeMensagem([
            'instance' => 'clinica-outra',
            'apikey' => 'token-da-outra',
        ]), $this->comSegredo())->assertOk();

        $this->conectar($outra['clinic']);

        $this->assertSame('5511999999999', WhatsAppMessage::query()->firstOrFail()->telefone());
        $this->conectar($malheiro);

        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function clinicaComEvolucao(): Clinic
    {
        $clinica = $this->criarClinica('Clínica Malheiro', 'malheiro@teste.test')['clinic'];

        $clinica->update([
            'evolution_instance' => 'malheiro',
            'evolution_token' => self::TOKEN_INSTANCIA,
        ]);

        return $clinica;
    }

    /**
     * @return array<string, string>
     */
    protected function comSegredo(): array
    {
        return ['X-Evolution-Secret' => self::SEGREDO];
    }

    /**
     * Payload mínimo de `messages.upsert`, com o formato de unix em SEGUNDOS que
     * o WhatsApp manda.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payloadDeMensagem(array $overrides = []): array
    {
        $padrao = [
            'event' => 'messages.upsert',
            'instance' => 'malheiro',
            'data' => [
                'key' => [
                    'remoteJid' => '5511999999999@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => '3EB0ABC123',
                ],
                'pushName' => 'Maria Souza',
                'messageType' => 'conversation',
                'messageTimestamp' => 1772323200,
                'message' => ['conversation' => 'Bom dia, quero marcar uma consulta'],
            ],
        ];

        // `data` é mesclado por chave: os testes trocam só `key.remoteJid` sem
        // reescrever o resto do evento.
        $data = array_replace_recursive($padrao['data'], $overrides['data'] ?? []);
        unset($overrides['data']);

        return array_replace($padrao, $overrides, ['data' => $data]);
    }
}
