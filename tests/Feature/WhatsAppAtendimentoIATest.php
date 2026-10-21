<?php

namespace Tests\Feature;

use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\Central\UsageMetric;
use App\Models\Professional;
use App\Models\WhatsAppConversation;
use App\Services\WhatsApp\CotaIA;
use App\Support\IntegrationAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * O atendimento do WhatsApp com IA.
 *
 * Nenhum destes testes fala com um provedor de verdade: `Http::fake` responde
 * por ele e `preventStrayRequests` transforma qualquer chamada não prevista em
 * falha. É a mesma regra da suíte do WhatsApp — nenhuma mensagem real sai daqui.
 *
 * O que se prova aqui não é "a IA responde bonito", e sim a fronteira: a IA
 * PROPÕE, o Laravel DECIDE. Por isso a maioria dos casos verifica que uma
 * proposta ausente, inválida ou sem cota termina no fluxo determinístico, e que
 * a ação de agenda nunca nasce do texto do modelo.
 */
class WhatsAppAtendimentoIATest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private string $token = 'token-ia-teste';

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
            'services.atendimento_ia.max_por_conversa' => 30,
        ]);
    }

    /**
     * Clínica com plano de IA, token e um profissional.
     *
     * @param  bool  $comIA  quando false, o plano NÃO tem `ai_agent` — é o que
     *                       prova que a rota não libera a cota para todo mundo
     * @param  int|null  $limite  teto mensal; null deixa a feature sem limite
     * @param  list<string>|null  $abilities  null dá acesso completo
     */
    private function criarContexto(bool $comIA = true, ?int $limite = null, ?array $abilities = null): Clinic
    {
        $clinica = $this->criarClinicaTeste('Clínica IA');

        Professional::create(['nome' => 'Dra. Ana', 'is_active' => true]);

        foreach (['agendado', 'confirmado', 'cancelado'] as $name) {
            AppointmentStatus::create(['name' => $name, 'label' => ucfirst($name)]);
        }

        $clinica = $this->assinarPlanoComWhatsapp($clinica);

        if ($comIA) {
            $plan = $clinica->latestSubscription->plan;
            $plan->features()->create([
                'feature' => 'ai_agent',
                'limits' => $limite === null ? [] : ['ai_messages_per_month' => $limite],
            ]);
        }

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

    /**
     * Conversa já adiantada, para o texto chegar onde se quer testar. A etapa
     * `consentimento` sempre delega, então os casos de IA precisam de uma etapa
     * posterior.
     *
     * @param  array<string, mixed>  $dados
     */
    private function conversa(string $jid, string $etapa = 'menu', array $dados = [], string $botStatus = 'ativo'): WhatsAppConversation
    {
        return WhatsAppConversation::create([
            'remote_jid' => $jid,
            'etapa' => $etapa,
            'dados' => $dados,
            'bot_status' => $botStatus,
            'expires_at' => now()->addHours(4),
        ]);
    }

    /**
     * @param  array{intencao?: string, resposta?: string, dados?: array<string, mixed>}  $proposta
     */
    private function fakeIA(array $proposta, int $status = 200): void
    {
        Http::fake([
            'openrouter.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($proposta)]]],
                'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 7],
            ], $status),
        ]);
    }

    public function test_ia_desligada_usa_o_fluxo_sem_chamar_provedor(): void
    {
        Http::fake();
        config(['services.atendimento_ia.enabled' => false]);

        $this->criarContexto();
        $this->conversa('5511999998888@s.whatsapp.net');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => 'oi, tudo bem?',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'fluxo');

        Http::assertNothingSent();
    }

    public function test_consentimento_nao_passa_pela_ia(): void
    {
        Http::fake();

        $this->criarContexto();

        // Conversa nova nasce no consentimento (LGPD): o texto não é
        // interpretado por terceiro, o fluxo é que responde.
        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => 'quanto custa a consulta?',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'fluxo');

        Http::assertNothingSent();
    }

    public function test_selecao_numerica_delega_ao_fluxo(): void
    {
        Http::fake();

        $this->criarContexto();
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        // "1" é escolha da lista, não texto livre: adivinhar isso com o modelo
        // seria arriscar a opção.
        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'fluxo')
            ->assertJsonPath('etapa_para', 'profissional');

        Http::assertNothingSent();
    }

    public function test_saudacao_responde_com_texto_da_ia_e_registra_consumo(): void
    {
        $this->fakeIA(['intencao' => 'saudacao', 'resposta' => 'Olá! Em que posso ajudar?']);

        $clinica = $this->criarContexto();
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => 'bom dia!',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'ia')
            ->assertJsonPath('intencao', 'saudacao')
            ->assertJsonPath('resposta', 'Olá! Em que posso ajudar?');

        $this->assertSame(1, UsageMetric::query()
            ->where('clinic_id', $clinica->id)
            ->value('ai_requests'));

        $this->assertSame(11, UsageMetric::query()
            ->where('clinic_id', $clinica->id)
            ->value('ai_input_tokens'));

        $this->assertSame(7, UsageMetric::query()
            ->where('clinic_id', $clinica->id)
            ->value('ai_output_tokens'));
    }

    public function test_intencao_fora_do_catalogo_cai_no_fluxo(): void
    {
        $this->fakeIA(['intencao' => 'fazer_pix', 'resposta' => 'Claro!']);

        $this->criarContexto();
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => 'queria pagar por pix',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'fluxo');
    }

    public function test_provedor_com_erro_cai_no_fluxo(): void
    {
        Http::fake([
            'openrouter.test/*' => Http::response('indisponível', 500),
        ]);

        $this->criarContexto();
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => 'bom dia',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'fluxo');
    }

    public function test_cota_esgotada_nao_chama_a_ia(): void
    {
        Http::fake();

        $clinica = $this->criarContexto(limite: 1);
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        UsageMetric::record($clinica, 'ai_requests');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => 'bom dia',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'fluxo');

        Http::assertNothingSent();
    }

    public function test_pedido_de_humano_pausa_o_bot(): void
    {
        $this->fakeIA(['intencao' => 'falar_humano', 'resposta' => 'Já chamei a equipe.']);

        $this->criarContexto();
        $jid = '5511999998888@s.whatsapp.net';
        $this->conversa($jid, 'menu');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), ['remote_jid' => $jid, 'texto' => 'quero falar com uma pessoa'])
            ->assertOk()
            ->assertJsonPath('handled_by', 'ia');

        $this->assertTenantDatabaseHas('whatsapp_conversations', [
            'remote_jid' => $jid,
            'bot_status' => 'aguardando_atendente',
        ]);

        // Com o bot pausado, a próxima mensagem não recebe resposta automática.
        // É o ponto do handoff: o bot cala para o humano falar.
        Http::fake();

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), ['remote_jid' => $jid, 'texto' => 'oi de novo'])
            ->assertOk()
            ->assertJsonPath('auto_resposta', false)
            ->assertJsonPath('handled_by', 'humano');

        Http::assertNothingSent();
    }

    public function test_agendar_delega_ao_fluxo_e_nao_agenda_por_conta(): void
    {
        $this->fakeIA(['intencao' => 'agendar', 'resposta' => 'Claro, vamos agendar.']);

        $this->criarContexto();
        $jid = '5511999998888@s.whatsapp.net';
        $this->conversa($jid, 'menu');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), ['remote_jid' => $jid, 'texto' => 'quero marcar uma consulta'])
            ->assertOk()
            ->assertJsonPath('handled_by', 'fluxo')
            ->assertJsonPath('etapa_para', 'profissional');

        // Nenhuma consulta criada por causa do texto do modelo.
        $this->assertTenantDatabaseCount('appointments', 0);
    }

    public function test_intencao_informativa_responde_com_dados_reais(): void
    {
        $this->fakeIA(['intencao' => 'profissionais', 'resposta' => '']);

        $this->criarContexto();
        $jid = '5511999998888@s.whatsapp.net';

        // Fora do menu, a resposta informativa não vira ação: apenas lista o que
        // existe no banco.
        $this->conversa($jid, 'horario');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), ['remote_jid' => $jid, 'texto' => 'quais dentistas atendem?'])
            ->assertOk()
            ->assertJsonPath('handled_by', 'ia')
            ->assertJsonPath('intencao', 'profissionais');

        $this->assertStringContainsString('Dra. Ana', $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), ['remote_jid' => $jid, 'texto' => 'e os dentistas?'])
            ->json('resposta'));
    }

    public function test_handoff_e_retomar_controlam_o_bot(): void
    {
        $this->criarContexto(abilities: ['conversations.handoff']);
        $jid = '5511999998888@s.whatsapp.net';

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.handoff'), ['remote_jid' => $jid])
            ->assertOk()
            ->assertJsonPath('conversa.bot_status', 'humano');

        $this->assertTenantDatabaseHas('whatsapp_conversations', [
            'remote_jid' => $jid,
            'bot_status' => 'humano',
        ]);

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.retomar'), ['remote_jid' => $jid])
            ->assertOk()
            ->assertJsonPath('conversa.bot_status', 'ativo');

        $this->assertTenantDatabaseHas('whatsapp_conversations', [
            'remote_jid' => $jid,
            'bot_status' => 'ativo',
        ]);
    }

    public function test_handoff_exige_ability(): void
    {
        $this->criarContexto(abilities: ['appointments.view']);

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.handoff'), ['remote_jid' => '5511999998888@s.whatsapp.net'])
            ->assertForbidden();
    }

    public function test_rota_de_ia_exige_ability_propria(): void
    {
        Http::fake();

        $this->criarContexto(abilities: ['conversations.advance']);
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        // Conduzir a conversa determinística não pode, sozinho, autorizar o
        // gasto da cota de IA nem o envio de dados ao provedor.
        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), ['remote_jid' => '5511999998888@s.whatsapp.net', 'texto' => 'oi'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_plano_sem_ai_agent_nao_libera_a_ia(): void
    {
        Http::fake();

        $this->criarContexto(comIA: false);
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), ['remote_jid' => '5511999998888@s.whatsapp.net', 'texto' => 'oi'])
            ->assertForbidden()
            ->assertJson(['feature' => 'ai_agent']);

        Http::assertNothingSent();
    }

    public function test_timeout_do_provedor_cai_no_fluxo(): void
    {
        Http::fake([
            'openrouter.test/*' => function ($request) {
                throw new ConnectionException('Tempo limite excedido', $request);
            },
        ]);

        $this->criarContexto();
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => 'bom dia',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'fluxo');
    }

    public function test_resposta_com_json_invalido_cai_no_fluxo(): void
    {
        Http::fake([
            'openrouter.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'infelizmente ainda não leio isso']]],
                'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 1],
            ], 200),
        ]);

        $this->criarContexto();
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => 'bom dia',
            ])
            ->assertOk()
            ->assertJsonPath('handled_by', 'fluxo');
    }

    public function test_cota_nao_estoura_o_limite_sob_corrida(): void
    {
        $clinica = $this->criarContexto(limite: 3);
        $cota = new CotaIA;

        $concedidas = 0;

        for ($i = 0; $i < 8; $i++) {
            if ($cota->reservar($clinica)) {
                $concedidas++;
            }
        }

        // O lock por clínica serializa a decisão: mesmo em sequência apertada, a
        // cota de 3 não passa de 3 e o contador não estoura.
        $this->assertSame(3, $concedidas);
        $this->assertSame(3, $cota->consumo($clinica));
    }

    public function test_cota_sem_limite_configurado_nao_nega(): void
    {
        $clinica = $this->criarContexto(limite: null);
        $cota = new CotaIA;

        // Feature sem teto é decisão de negócio ("IA sem contagem"), não defeito.
        $this->assertTrue($cota->reservar($clinica));
        $this->assertSame(1, $cota->consumo($clinica));
    }

    public function test_cota_rejeita_assinatura_inativa(): void
    {
        $clinica = $this->criarContexto(limite: 10);
        $clinica->latestSubscription->update(['status' => 'cancelled']);

        $cota = new CotaIA;

        $this->assertFalse($cota->disponivel($clinica->fresh()));
        $this->assertFalse($cota->reservar($clinica->fresh()));
    }

    public function test_assinatura_inativa_nega_a_rota_de_ia(): void
    {
        Http::fake();

        $clinica = $this->criarContexto();
        $clinica->latestSubscription->update(['status' => 'cancelled']);
        $this->conversa('5511999998888@s.whatsapp.net', 'menu');

        $this->comoIntegracao()
            ->postJson(route('api.whatsapp.ia'), [
                'remote_jid' => '5511999998888@s.whatsapp.net',
                'texto' => 'bom dia',
            ])
            ->assertForbidden()
            ->assertJson(['feature' => 'subscription']);

        Http::assertNothingSent();
    }
}
