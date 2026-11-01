<?php

namespace Tests\Feature;

use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Room;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppOutboxMessage;
use App\Services\WhatsApp\ClinicFeatures;
use App\Support\IntegrationAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Menus de botões/lista da Fase 3.
 *
 * O contrato tem duas metades e as duas importam:
 *
 * 1. com `interactive_menus` desligada, `/fluxo` devolve EXATAMENTE o que
 *    devolvia antes — nenhuma chave nova, nenhuma chamada à Evolution;
 * 2. com a flag ligada, o fluxo devolve `interativo` e a rota de envio fala com
 *    `sendButtons`/`sendList` da Evolution 2.3.7, caindo para o texto numerado
 *    quando o formato é recusado.
 *
 * O fallback é o ponto mais importante: botão depende do aparelho do paciente.
 * Se a Evolution recusar, o paciente ainda precisa receber o menu em texto.
 */
class WhatsAppMenusInterativosTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private string $token = 'token-menus';

    private string $jid = '5599999999999@s.whatsapp.net';

    private Professional $profissional;

    private Procedure $procedimento;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.webhook_secret' => 'segredo',
        ]);
    }

    /**
     * @param  list<string>  $flags
     * @param  list<string>|null  $abilities
     */
    private function criarContexto(array $flags = [], ?array $abilities = null): Clinic
    {
        $clinica = $this->criarClinicaTeste('Clínica Menu');

        $clinica->update([
            'evolution_instance' => 'clinica-menu',
            'evolution_token' => 'token-instancia',
        ]);

        $this->profissional = Professional::create([
            'nome' => 'Dra. Ana',
            'is_active' => true,
        ]);

        $this->procedimento = Procedure::create([
            'codigo' => 'CONSULTA',
            'nome' => 'Consulta',
            'duracao_minutos' => 30,
            'is_active' => true,
        ]);

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            ProfessionalSchedule::create([
                'professional_id' => $this->profissional->id,
                'weekday' => $weekday,
                'start_time' => '08:00',
                'end_time' => '12:00',
            ]);
        }

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

        foreach ($flags as $flag) {
            app(ClinicFeatures::class)->set($clinica, $flag, true);
        }

        return $clinica;
    }

    /**
     * @return array<string, mixed>
     */
    private function dizer(string $texto): array
    {
        $resposta = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.fluxo'), [
                'remote_jid' => $this->jid,
                'texto' => $texto,
            ]);

        $resposta->assertOk();

        return $resposta->json();
    }

    /**
     * Leva a conversa até o menu, depois do cadastro.
     *
     * @return array<string, mixed>
     */
    private function ateMenu(): array
    {
        $this->dizer('oi');
        $this->dizer('1');

        return $this->dizer('João da Silva');
    }

    /**
     * @return array<string, mixed>
     */
    private function ateProfissional(): array
    {
        $this->ateMenu();

        return $this->dizer('1');
    }

    /**
     * @return array<string, mixed>
     */
    private function ateProcedimento(): array
    {
        $this->ateProfissional();

        return $this->dizer('1');
    }

    /**
     * @return array<string, mixed>
     */
    private function ateData(): array
    {
        $this->ateProcedimento();

        return $this->dizer('1');
    }

    /**
     * @return array<string, mixed>
     */
    private function ateHorario(): array
    {
        $this->ateData();

        return $this->dizer('1');
    }

    /**
     * @param  array<string, mixed>  $interativo
     */
    private function enviarMenu(array $interativo): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson(route('api.whatsapp.mensagem-interativa'), [
                'telefone' => '5511988887777',
                'interativo' => $interativo,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function menuDeBotoes(): array
    {
        return [
            'tipo' => 'buttons',
            'titulo' => 'Como posso ajudar?',
            'descricao' => 'Toque em uma opção abaixo.',
            'opcoes' => [
                ['id' => '1', 'titulo' => 'Agendar consulta', 'descricao' => 'Escolher horário.'],
                ['id' => '2', 'titulo' => 'Falar com a recepção'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function menuDeLista(): array
    {
        return [
            'tipo' => 'list',
            'titulo' => 'Escolha o horário',
            'descricao' => 'Toque em um horário abaixo.',
            'opcoes' => [
                ['id' => '1', 'titulo' => '08:00'],
                ['id' => '2', 'titulo' => '08:30'],
            ],
        ];
    }

    public function test_flag_desligada_nao_inclui_o_contrato_interativo(): void
    {
        $this->criarContexto();

        $r = $this->dizer('oi');

        $this->assertSame('consentimento', $r['etapa_para']);
        $this->assertStringContainsString('LGPD', $r['resposta']);
        $this->assertArrayNotHasKey('interativo', $r);
    }

    public function test_flag_ligada_devolve_botoes_no_consentimento(): void
    {
        $this->criarContexto(['interactive_menus']);

        $r = $this->dizer('oi');

        $this->assertSame('consentimento', $r['etapa_para']);
        // O texto continua completo: o n8n atual segue funcionando sem mudar.
        $this->assertStringContainsString('LGPD', $r['resposta']);

        $this->assertSame('buttons', $r['interativo']['tipo']);
        $this->assertCount(2, $r['interativo']['opcoes']);
        $this->assertSame(['1', '2'], array_column($r['interativo']['opcoes'], 'id'));
    }

    public function test_menu_depois_do_cadastro_tambem_vira_botoes(): void
    {
        $this->criarContexto(['interactive_menus']);

        $r = $this->ateMenu();

        $this->assertSame('menu', $r['etapa_para']);
        $this->assertSame('buttons', $r['interativo']['tipo']);
        $this->assertSame('1', $r['interativo']['opcoes'][0]['id']);
        $this->assertSame('Agendar consulta', $r['interativo']['opcoes'][0]['titulo']);
    }

    public function test_lista_de_profissionais_usa_a_posicao_como_id(): void
    {
        $this->criarContexto(['interactive_menus']);

        foreach (['Dr. Bruno', 'Dra. Carla', 'Dr. Diego'] as $nome) {
            Professional::create(['nome' => $nome, 'is_active' => true]);
        }

        $r = $this->ateProfissional();

        $this->assertSame('profissional', $r['etapa_para']);
        $this->assertSame('list', $r['interativo']['tipo']);
        $this->assertCount(4, $r['interativo']['opcoes']);
        $this->assertSame(['1', '2', '3', '4'], array_column($r['interativo']['opcoes'], 'id'));

        // O fluxo lista por `nome`, então a ordem da tela não é a ordem de
        // criação. O id é a posição que o paciente viu, não o id do banco: é
        // ele que o webhook devolve no `selectedRowId` e que a máquina já sabe
        // ler. Se o id fosse `profissionais.id`, o "1" apontaria para outra
        // pessoa e a escolha sairia errada.
        $this->assertSame(
            ['Dr. Bruno', 'Dr. Diego', 'Dra. Ana', 'Dra. Carla'],
            array_column($r['interativo']['opcoes'], 'titulo'),
        );
    }

    public function test_titulos_repetidos_sao_desambiguados(): void
    {
        $this->criarContexto(['interactive_menus']);

        // Dois profissionais com o mesmo nome gerariam dois botões com o mesmo
        // texto, e a Evolution recusa o menu inteiro. O sufixo mantém a
        // mensagem válida; a escolha continua sendo o `id` da posição.
        Professional::create(['nome' => 'Dra. Ana', 'is_active' => true]);

        $r = $this->ateProfissional();
        $titulos = array_column($r['interativo']['opcoes'], 'titulo');

        $this->assertCount(2, $titulos);
        $this->assertCount(2, array_unique($titulos));
        $this->assertContains('Dra. Ana ·2', $titulos);
    }

    public function test_lista_de_procedimentos_respeita_o_limite_de_dez_linhas(): void
    {
        $this->criarContexto(['interactive_menus']);

        foreach (range(2, 12) as $numero) {
            Procedure::create([
                'codigo' => 'PROC'.$numero,
                'nome' => 'Procedimento '.$numero,
                'duracao_minutos' => 30,
                'is_active' => true,
            ]);
        }

        $r = $this->ateProcedimento();

        $this->assertSame('procedimento', $r['etapa_para']);
        $this->assertSame('list', $r['interativo']['tipo']);
        $this->assertCount(10, $r['interativo']['opcoes']);
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'], array_column($r['interativo']['opcoes'], 'id'));
    }

    public function test_tela_de_dia_vira_lista_com_os_dias_guardados(): void
    {
        $this->criarContexto(['interactive_menus']);

        $r = $this->ateData();
        $dados = WhatsAppConversation::sole()->dados;

        $this->assertSame('data', $r['etapa_para']);
        $this->assertSame('list', $r['interativo']['tipo']);
        $this->assertCount(count($dados['dias']), $r['interativo']['opcoes']);
        $this->assertSame('1', $r['interativo']['opcoes'][0]['id']);
    }

    public function test_tela_de_horario_vira_lista_com_os_horarios_visiveis(): void
    {
        $this->criarContexto(['interactive_menus']);

        $r = $this->ateHorario();
        $dados = WhatsAppConversation::sole()->dados;

        $this->assertSame('horario', $r['etapa_para']);
        $this->assertSame('list', $r['interativo']['tipo']);
        $this->assertCount(count($dados['horarios']), $r['interativo']['opcoes']);
        $this->assertSame($dados['horarios'][0], $r['interativo']['opcoes'][0]['titulo']);
    }

    public function test_flag_desligada_recusa_o_envio_interativo_e_devolve_o_texto(): void
    {
        Http::fake();

        $this->criarContexto();

        $this->enviarMenu($this->menuDeBotoes())
            ->assertStatus(409)
            ->assertJsonPath('status', 'disabled')
            ->assertJsonStructure(['fallback_texto']);

        Http::assertNothingSent();
        $this->assertTenantDatabaseCount('whatsapp_messages', 0);
    }

    public function test_envia_botoes_para_a_evolution_e_registra_historico(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0MENU']], 201)]);

        $this->criarContexto(['interactive_menus']);

        $this->enviarMenu($this->menuDeBotoes())
            ->assertOk()
            ->assertJsonPath('status', 'sent')
            ->assertJsonPath('tipo', 'buttons');

        Http::assertSent(function (Request $request): bool {
            $dados = $request->data();

            return str_ends_with($request->url(), '/message/sendButtons/clinica-menu')
                && ($dados['number'] ?? null) === '5511988887777'
                && ($dados['title'] ?? null) === 'Como posso ajudar?'
                && count($dados['buttons'] ?? []) === 2
                && ($dados['buttons'][0]['type'] ?? null) === 'reply'
                && ($dados['buttons'][0]['id'] ?? null) === '1'
                && ($dados['buttons'][1]['displayText'] ?? null) === 'Falar com a recepção';
        });

        // O histórico guarda o texto equivalente: é o que a recepção consegue
        // ler na Fase 4, quando o painel mostrar a conversa.
        $mensagem = WhatsAppMessage::sole();

        $this->assertStringContainsString('Responda com o número.', (string) $mensagem->body);
        $this->assertSame('3EB0MENU', $mensagem->message_key);
    }

    public function test_envia_lista_com_o_schema_exigido_pela_evolution(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0LISTA']], 201)]);

        $this->criarContexto(['interactive_menus']);

        $this->enviarMenu($this->menuDeLista())
            ->assertOk()
            ->assertJsonPath('status', 'sent')
            ->assertJsonPath('tipo', 'list');

        Http::assertSent(function (Request $request): bool {
            $dados = $request->data();

            return str_ends_with($request->url(), '/message/sendList/clinica-menu')
                && ($dados['buttonText'] ?? null) === 'Ver opções'
                && ($dados['footerText'] ?? null) !== ''
                && count($dados['sections'] ?? []) === 1
                && ($dados['sections'][0]['rows'][1]['rowId'] ?? null) === '2';
        });
    }

    public function test_recusa_do_formato_interativo_dispara_o_fallback_textual(): void
    {
        Http::fakeSequence('https://evolution.test/*')
            ->push(['message' => 'buttons not supported'], 400)
            ->push(['key' => ['id' => '3EB0TEXTO']], 201);

        $this->criarContexto(['interactive_menus']);

        $this->enviarMenu($this->menuDeBotoes())
            ->assertOk()
            ->assertJsonPath('status', 'sent_fallback');

        Http::assertSentCount(2);

        $gravadas = Http::recorded();

        $this->assertStringContainsString('/message/sendButtons/', $gravadas[0][0]->url());
        $this->assertStringContainsString('/message/sendText/', $gravadas[1][0]->url());

        $mensagem = WhatsAppMessage::sole();

        $this->assertStringContainsString('1. Agendar consulta', (string) $mensagem->body);
        $this->assertSame('3EB0TEXTO', $mensagem->message_key);
    }

    public function test_erro_no_corpo_da_evolution_tambem_dispara_o_fallback(): void
    {
        Http::fakeSequence('https://evolution.test/*')
            ->push(['error' => 'invalid button'], 200)
            ->push(['key' => ['id' => '3EB0TEXTO2']], 201);

        $this->criarContexto(['interactive_menus']);

        $this->enviarMenu($this->menuDeBotoes())
            ->assertOk()
            ->assertJsonPath('status', 'sent_fallback');

        Http::assertSentCount(2);
    }

    public function test_timeout_nao_dispara_fallback_para_nao_duplicar(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('cURL error 28: Operation timed out after 30001 milliseconds');
        });

        $this->criarContexto(['interactive_menus']);

        $this->enviarMenu($this->menuDeBotoes())->assertStatus(502);

        // Se o menu pode ter saído, mandar o texto duplicaria a mensagem no
        // celular do paciente. A decisão é a mesma da outbox: DLQ/revisão.
        // (A exceção do fake não entra em `Http::recorded()`, então o que se
        // prova aqui é o efeito: nenhum histórico e nenhum segundo envio.)
        $this->assertTenantDatabaseCount('whatsapp_messages', 0);
        $this->assertTenantDatabaseCount('whatsapp_outbox', 0);
    }

    public function test_outbox_guarda_o_menu_e_envia_como_botao(): void
    {
        Http::fake(['https://evolution.test/*' => Http::response(['key' => ['id' => '3EB0OUTBOXMENU']], 201)]);

        $this->criarContexto(['interactive_menus', 'whatsapp_outbox']);

        $this->enviarMenu($this->menuDeBotoes())
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        $outbox = WhatsAppOutboxMessage::sole();

        $this->assertSame('buttons', $outbox->kind);
        $this->assertSame('buttons', $outbox->payload['tipo']);
        $this->assertSame(WhatsAppOutboxMessage::SENT, $outbox->status);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/message/sendButtons/clinica-menu'));
    }

    public function test_outbox_que_recusa_o_menu_reenvia_texto_na_mesma_linha(): void
    {
        Http::fakeSequence('https://evolution.test/*')
            ->push(['message' => 'buttons not supported'], 400)
            ->push(['key' => ['id' => '3EB0OUTBOXTEXTO']], 201);

        $this->criarContexto(['interactive_menus', 'whatsapp_outbox']);

        $this->enviarMenu($this->menuDeBotoes())
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        $outbox = WhatsAppOutboxMessage::sole();

        $this->assertSame('text', $outbox->kind);
        $this->assertNull($outbox->payload);
        $this->assertSame(WhatsAppOutboxMessage::SENT, $outbox->status);
        $this->assertSame('interativo_recusado_fallback', $outbox->last_error_code);
        $this->assertSame('3EB0OUTBOXTEXTO', $outbox->provider_message_id);

        Http::assertSentCount(2);
        $this->assertTenantDatabaseCount('whatsapp_messages', 1);
    }

    public function test_botoes_acima_do_limite_sao_recusados_na_validacao(): void
    {
        Http::fake();

        $this->criarContexto(['interactive_menus']);

        $menu = $this->menuDeBotoes();
        $menu['opcoes'] = [
            ['id' => '1', 'titulo' => 'Um'],
            ['id' => '2', 'titulo' => 'Dois'],
            ['id' => '3', 'titulo' => 'Três'],
            ['id' => '4', 'titulo' => 'Quatro'],
        ];

        $this->enviarMenu($menu)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('interativo.opcoes');

        Http::assertNothingSent();
    }

    public function test_token_sem_ability_de_envio_e_recusado(): void
    {
        $this->criarContexto(['interactive_menus'], ['appointments.view']);

        $this->enviarMenu($this->menuDeBotoes())->assertForbidden();

        Http::assertNothingSent();
    }
}
