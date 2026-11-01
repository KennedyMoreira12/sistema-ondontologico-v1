<?php

namespace Tests\Feature;

use App\Models\Central\UsageMetric;
use App\Models\WhatsAppConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Isolamento da IA por clínica.
 *
 * O token de integração é por clínica, e com ele a IA desconta a cota e lê o
 * histórico da MESMA clínica: o token da B não pode gastar a cota da A nem
 * encontrar a conversa da A. Aqui cada clínica tem seu próprio arquivo sqlite
 * e seu próprio `usage_metrics` no banco central.
 */
class WhatsAppAtendimentoIAIsolamentoTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.webhook_secret' => 'segredo',
            'services.atendimento_ia.enabled' => true,
            'services.atendimento_ia.api_key' => 'chave-de-teste',
            'services.atendimento_ia.base_url' => 'https://openrouter.test/api/v1',
            'services.atendimento_ia.model' => 'modelo-de-teste',
            'services.atendimento_ia.timeout' => 5,
        ]);
    }

    public function test_token_de_uma_clinica_usa_a_cota_e_o_banco_dela_apenas(): void
    {
        Http::fake([
            'openrouter.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'intencao' => 'saudacao',
                    'resposta' => 'Olá!',
                ])]]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
            ], 200),
        ]);

        $a = $this->criarClinica('Clínica A', 'a@teste.test', comAdmin: false);
        $b = $this->criarClinica('Clínica B', 'b@teste.test', comAdmin: false);

        // O plano é global, então liberar `ai_agent` vale para as duas clínicas.
        // O que é por clínica é a cota descontada e o histórico de conversa.
        $plano = $a['clinic']->latestSubscription->plan;

        if (! $plano->hasFeature('ai_agent')) {
            $plano->features()->create([
                'feature' => 'ai_agent',
                'limits' => ['ai_messages_per_month' => 50],
            ]);
        }

        $jid = '5511999998888@s.whatsapp.net';
        $conversa = [
            'remote_jid' => $jid,
            'etapa' => 'menu',
            'dados' => [],
            'bot_status' => 'ativo',
            'expires_at' => now()->addHours(4),
        ];

        // Só a clínica B tem a conversa em andamento.
        $this->conectar($b['clinic']);
        WhatsAppConversation::create($conversa);

        $this->withHeader('Authorization', 'Bearer '.$b['token'])
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => $jid,
                'texto' => 'bom dia!',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'ia');

        // O consumo ficou na conta da B; a A não gastou nada.
        $this->assertSame(1, UsageMetric::where('clinic_id', $b['clinic']->id)->value('ai_requests'));
        $this->assertNull(UsageMetric::where('clinic_id', $a['clinic']->id)->value('ai_requests'));

        // O histórico da conversa existe no banco da B, e não no da A.
        $this->conectar($a['clinic']);
        $this->assertDatabaseMissing('whatsapp_conversations', ['remote_jid' => $jid], 'tenant');

        $this->conectar($b['clinic']);
        $this->assertDatabaseHas('whatsapp_conversations', ['remote_jid' => $jid], 'tenant');
    }
}
