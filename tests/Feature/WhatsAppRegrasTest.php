<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\WhatsAppAlert;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppNote;
use App\Models\WhatsAppQuickReply;
use App\Models\WhatsAppRule;
use App\Services\WhatsApp\ConfiguracaoWhatsApp;
use App\Support\IntegrationAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Regras automáticas, expediente, opt-out e a fila da recepção.
 *
 * O que se prova aqui é a precedência, não a redação da resposta: opt-out vence
 * tudo, pausa/humano vence regra, expediente vence regra e regra vence o fluxo
 * — mas nunca dentro de um passo com contrato (cadastro, escolha de horário).
 * Nenhuma mensagem real é enviada.
 */
class WhatsAppRegrasTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private string $token = 'token-regras';

    private string $jid = '5511988887777@s.whatsapp.net';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.webhook_secret' => 'segredo',
            'whatsapp.business_hours.absence_message' => 'Estamos fechados agora. Deixe sua mensagem que retornamos.',
            'whatsapp.business_hours.absence_reply_cooldown_minutes' => 60,
        ]);
    }

    private function criarContexto(?array $abilities = null): Clinic
    {
        $clinica = $this->criarClinicaTeste('Clínica Regras');
        $clinica = $this->assinarPlanoComWhatsapp($clinica);

        IntegrationToken::create([
            'clinic_id' => $clinica->id,
            'name' => 'n8n',
            'token_hash' => IntegrationToken::hashToken($this->token),
            'abilities' => $abilities ?? [IntegrationAbilities::WILDCARD],
        ]);

        return $clinica;
    }

    private function conversa(string $etapa = 'menu', string $jid = ''): WhatsAppConversation
    {
        return WhatsAppConversation::create([
            'remote_jid' => $jid !== '' ? $jid : $this->jid,
            'etapa' => $etapa,
            'dados' => null,
            'bot_status' => 'ativo',
            'expires_at' => now()->addHours(4),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dizer(string $texto, ?string $jid = null): array
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.fluxo'), [
                'remote_jid' => $jid ?? $this->jid,
                'texto' => $texto,
            ])
            ->assertOk()
            ->json();
    }

    private function regra(array $atributos): WhatsAppRule
    {
        return WhatsAppRule::create(array_merge([
            'nome' => 'Regra de teste',
            'tipo' => WhatsAppRule::CONTAINS,
            'gatilho' => 'endereco',
            'resposta' => 'Estamos na Rua das Flores, 100.',
            'prioridade' => 100,
            'escopo' => WhatsAppRule::ESCOPO_LIVRE,
            'is_active' => true,
        ], $atributos));
    }

    private function definirExpediente(array $horarios): void
    {
        app(ConfiguracaoWhatsApp::class)->definirJson(ConfiguracaoWhatsApp::EXPEDIENTE, $horarios);
    }

    /**
     * @return array<string, list<array{0: string, 1: string}>>
     */
    private function semanaComercial(): array
    {
        $horarios = [];

        foreach (range(1, 5) as $dia) {
            $horarios[(string) $dia] = [['08:00', '18:00']];
        }

        return $horarios;
    }

    public function test_regra_exact_responde_e_nao_avanca_o_fluxo(): void
    {
        $this->criarContexto();
        $this->conversa();

        $this->regra([
            'tipo' => WhatsAppRule::EXACT,
            'gatilho' => 'Qual o endereço?',
            'resposta' => 'Estamos na Rua das Flores, 100.',
            'escopo' => WhatsAppRule::ESCOPO_MENU,
        ]);

        // Caixa, acento e espaços não mudam a pergunta.
        $resposta = $this->dizer('  qual o endereco? ');

        $this->assertSame('regra', $resposta['handled_by']);
        $this->assertSame('Estamos na Rua das Flores, 100.', $resposta['resposta']);
        $this->assertSame('menu', $resposta['etapa_para']);
        $this->assertSame('menu', WhatsAppConversation::first()->etapa);
    }

    public function test_regra_contains_respeita_prioridade_e_ignora_desativada(): void
    {
        $this->criarContexto();
        $this->conversa();

        $this->regra([
            'gatilho' => 'horario',
            'resposta' => 'Prioridade baixa.',
            'prioridade' => 50,
        ]);

        $this->regra([
            'gatilho' => 'horario',
            'resposta' => 'Prioridade alta.',
            'prioridade' => 10,
        ]);

        $this->regra([
            'gatilho' => 'horario',
            'resposta' => 'Regra desligada.',
            'prioridade' => 1,
            'is_active' => false,
        ]);

        $this->assertSame('Prioridade alta.', $this->dizer('qual o horario de voces?')['resposta']);
    }

    public function test_regex_invalida_e_ignorada_sem_derrubar_o_atendimento(): void
    {
        $this->criarContexto();
        $this->conversa('consentimento');

        $this->regra([
            'tipo' => WhatsAppRule::REGEX,
            'gatilho' => '/(/',
            'resposta' => 'Nunca deveria sair.',
        ]);

        $resposta = $this->dizer('oi');

        // A regra quebrada não casa; o fluxo segue normalmente.
        $this->assertSame('consentimento', $resposta['etapa_para']);
        $this->assertStringContainsString('LGPD', $resposta['resposta']);
    }

    public function test_regex_valida_responde(): void
    {
        $this->criarContexto();
        $this->conversa();

        $this->regra([
            'tipo' => WhatsAppRule::REGEX,
            'gatilho' => '/^(bom dia|boa tarde)/iu',
            'resposta' => 'Bom dia! Como podemos ajudar?',
        ]);

        $this->assertSame('Bom dia! Como podemos ajudar?', $this->dizer('Bom dia, tudo bem?')['resposta']);
    }

    public function test_regra_nao_atropela_passo_com_contrato(): void
    {
        $this->criarContexto();
        $this->conversa('data');

        $this->regra([
            'tipo' => WhatsAppRule::CONTAINS,
            'gatilho' => '1',
            'resposta' => 'A regra não pode responder isto.',
        ]);

        $resposta = $this->dizer('1');

        $this->assertSame('data', $resposta['etapa_para']);
        $this->assertStringContainsString('Opção inválida', $resposta['resposta']);
    }

    public function test_comando_global_nao_e_sequestrado_por_regra(): void
    {
        $this->criarContexto();
        $this->conversa('profissional');

        $this->regra([
            'tipo' => WhatsAppRule::CONTAINS,
            'gatilho' => 'menu',
            'resposta' => 'A regra não pode responder isto.',
        ]);

        $resposta = $this->dizer('menu');

        $this->assertSame('menu', $resposta['etapa_para']);
        $this->assertStringContainsString('Como posso ajudar', $resposta['resposta']);
    }

    public function test_fora_do_expediente_avisa_pausa_e_entra_na_fila_da_recepcao(): void
    {
        $this->criarContexto();
        $this->conversa();

        $this->definirExpediente($this->semanaComercial());

        // Segunda-feira, 20h no fuso da clínica (America/Sao_Paulo).
        $this->travelTo(Carbon::parse('2026-11-02 20:00:00', 'America/Sao_Paulo'));

        $resposta = $this->dizer('bom dia, preciso de ajuda');

        $this->assertSame('ausente', $resposta['handled_by']);
        $this->assertSame('Estamos fechados agora. Deixe sua mensagem que retornamos.', $resposta['resposta']);

        $conversa = WhatsAppConversation::first();

        $this->assertSame('pausado', $conversa->bot_status);
        $this->assertTrue(
            $conversa->pausado_ate->equalTo(Carbon::parse('2026-11-03 08:00:00', 'America/Sao_Paulo')),
            'A pausa por ausência deve terminar na próxima abertura.',
        );

        $alerta = WhatsAppAlert::first();

        $this->assertNotNull($alerta);
        $this->assertSame(WhatsAppAlert::FORA_EXPEDIENTE, $alerta->tipo);
        $this->assertNull($alerta->atendido_em);
    }

    public function test_bot_volta_sozinho_no_horario_de_abertura(): void
    {
        $this->criarContexto();
        $this->conversa();

        $this->definirExpediente($this->semanaComercial());

        $this->travelTo(Carbon::parse('2026-11-02 20:00:00', 'America/Sao_Paulo'));
        $this->dizer('bom dia');

        $this->assertSame('pausado', WhatsAppConversation::first()->bot_status);

        // Terça-feira, 9h: o prazo venceu e o bot volta a responder sem ninguém
        // clicar em "retomar".
        $this->travelTo(Carbon::parse('2026-11-03 09:00:00', 'America/Sao_Paulo'));

        $resposta = $this->dizer('bom dia');

        $this->assertNotSame('ausente', $resposta['handled_by'] ?? null);
        $this->assertSame('ativo', WhatsAppConversation::first()->bot_status);
    }

    public function test_expediente_usa_o_fuso_da_clinica_e_nao_o_da_aplicacao(): void
    {
        $clinica = $this->criarContexto();
        $clinica->update(['timezone' => 'America/Manaus']);

        $this->conversa();
        $this->definirExpediente($this->semanaComercial());

        // 20h UTC é 16h em Manaus: aberto. Se o fuso da aplicação (UTC) fosse
        // usado, a clínica apareceria fechada.
        $this->travelTo(Carbon::parse('2026-11-02 20:00:00', 'UTC'));

        $aberta = $this->dizer('oi');

        $this->assertNotSame('ausente', $aberta['handled_by'] ?? null);

        // 22h30 UTC é 18h30 em Manaus: fechado.
        $this->travelTo(Carbon::parse('2026-11-02 22:30:00', 'UTC'));

        $this->assertSame('ausente', $this->dizer('oi')['handled_by']);
    }

    public function test_opt_out_pausa_sem_prazo_e_so_volta_com_retomada_explicita(): void
    {
        $this->criarContexto();
        $this->conversa();

        $this->regra([
            'tipo' => WhatsAppRule::EXACT,
            'gatilho' => 'parar',
            'resposta' => 'A regra não pode responder isto.',
        ]);

        $resposta = $this->dizer('parar');

        $this->assertSame('opt_out', $resposta['handled_by']);
        $this->assertStringContainsString('Não vou mais enviar', $resposta['resposta']);

        $conversa = WhatsAppConversation::first();

        $this->assertSame('pausado', $conversa->bot_status);
        $this->assertNull($conversa->pausado_ate);

        // A regra existe, mas a pausa sem prazo vence: bot calado.
        $silencio = $this->dizer('parar');

        $this->assertSame('opt_out', $silencio['handled_by']);
        $this->assertSame('', $silencio['resposta']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.retomar'), ['remote_jid' => $this->jid])
            ->assertOk();

        $this->assertSame('ativo', WhatsAppConversation::first()->bot_status);
    }

    public function test_handoff_cala_o_bot_mesmo_sem_ia(): void
    {
        $this->criarContexto();
        $this->conversa();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.handoff'), ['remote_jid' => $this->jid])
            ->assertOk();

        $resposta = $this->dizer('oi');

        $this->assertSame('humano', $resposta['handled_by']);
        $this->assertSame('', $resposta['resposta']);
    }

    public function test_conversa_na_recepcao_abre_alerta_e_handoff_resolve(): void
    {
        $this->criarContexto();
        $this->conversa();

        // No menu atual: 1 = agendar, 2 = minhas consultas, 3 = recepção.
        $resultado = $this->dizer('3');

        $this->assertSame('recepcao', $resultado['etapa_para']);
        $this->assertSame(1, WhatsAppAlert::query()->abertos()->count());

        $fila = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson(route('api.whatsapp.alertas'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $fila['total']);
        $this->assertSame($this->jid, $fila['alertas'][0]['remote_jid']);
        $this->assertSame(WhatsAppAlert::HANDOFF, $fila['alertas'][0]['tipo']);
        $this->assertIsInt($fila['alertas'][0]['esperando_minutos']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.handoff'), ['remote_jid' => $this->jid])
            ->assertOk();

        $this->assertSame(0, WhatsAppAlert::query()->abertos()->count());
        $this->assertNotNull(WhatsAppAlert::first()->atendido_em);
    }

    public function test_nota_interna_fica_fora_do_historico_e_da_auditoria(): void
    {
        $this->criarContexto();
        $this->conversa();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.notas'), [
                'remote_jid' => $this->jid,
                'conteudo' => 'Paciente prefere contato à tarde.',
            ])
            ->assertCreated()
            ->assertJsonPath('nota.conteudo', 'Paciente prefere contato à tarde.');

        $this->assertTenantDatabaseHas('whatsapp_notes', [
            'remote_jid' => $this->jid,
            'conteudo' => 'Paciente prefere contato à tarde.',
        ]);

        // Nota não é mensagem: nada é gravado no histórico nem enviado.
        $this->assertTenantDatabaseCount('whatsapp_messages', 0);

        $auditoria = AuditLog::query()->where('acao', 'nota_interna')->first();

        $this->assertNotNull($auditoria);
        $this->assertArrayNotHasKey('conteudo', $auditoria->depois ?? []);

        $notas = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson(route('api.whatsapp.notas', ['remote_jid' => $this->jid]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $notas['notas']);
        $this->assertSame($this->jid, $notas['notas'][0]['remote_jid']);
    }

    public function test_respostas_rapidas_listam_apenas_as_ativas(): void
    {
        $this->criarContexto();

        WhatsAppQuickReply::create([
            'atalho' => 'endereco',
            'titulo' => 'Endereço',
            'conteudo' => 'Rua das Flores, 100.',
            'is_active' => true,
        ]);

        WhatsAppQuickReply::create([
            'atalho' => 'antiga',
            'titulo' => 'Resposta antiga',
            'conteudo' => 'Não deve aparecer.',
            'is_active' => false,
        ]);

        $respostas = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson(route('api.whatsapp.respostas-rapidas'))
            ->assertOk()
            ->json('respostas');

        $this->assertCount(1, $respostas);
        $this->assertSame('endereco', $respostas[0]['atalho']);
    }

    public function test_fila_e_notas_exigem_ability_de_atendimento_humano(): void
    {
        $this->criarContexto(abilities: ['appointments.view']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson(route('api.whatsapp.alertas'))
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.notas'), [
                'remote_jid' => $this->jid,
                'conteudo' => 'nota',
            ])
            ->assertForbidden();
    }

    public function test_nota_vazia_e_recusada(): void
    {
        $this->criarContexto();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.notas'), [
                'remote_jid' => $this->jid,
                'conteudo' => '   ',
            ])
            ->assertUnprocessable();

        $this->assertSame(0, WhatsAppNote::query()->count());
    }
}
