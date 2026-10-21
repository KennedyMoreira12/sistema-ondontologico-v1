<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Prospect;
use App\Models\Central\ProspectOrigin;
use App\Models\Central\ProspectWhatsAppSetting;
use App\Services\Platform\Prospeccao\ProspectImporter;
use App\Services\Platform\Prospeccao\ProspectWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * WhatsApp EXCLUSIVO da prospecção comercial.
 *
 * Toda a Evolution é simulada com `Http::fake()`: nenhum teste cria, consulta ou
 * desconecta instância de verdade. O que se protege: só o super admin entra; a
 * instância criada é sempre a de prospecção (nunca `malheiro` nem uma clínica);
 * o QR sai só no clique; erros não vazam a chave administrativa; e o n8n não é
 * tocado.
 */
class PlatformProspeccaoWhatsAppTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.admin_key' => 'chave-admin-secreta',
            'services.evolution.timeout' => 5,
        ]);

        $this->admin = AdminUser::factory()->create();
    }

    public function test_superadmin_ve_estado_nao_configurado_e_o_card_na_listagem(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->getJson(route('painel.prospeccao.whatsapp.show'))
            ->assertOk()
            ->assertJsonPath('configurado', false)
            ->assertJsonPath('status', ProspectWhatsAppService::STATUS_NOT_CONFIGURED);

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index'))
            ->assertOk()
            ->assertSee('WhatsApp de prospecção');
    }

    public function test_suporte_nao_acessa_nem_gera_qr(): void
    {
        $suporte = AdminUser::factory()->support()->create();

        $this->actingAs($suporte, 'admin')->getJson(route('painel.prospeccao.whatsapp.show'))->assertForbidden();
        $this->actingAs($suporte, 'admin')->postJson(route('painel.prospeccao.whatsapp.instancia'))->assertForbidden();
        $this->actingAs($suporte, 'admin')->postJson(route('painel.prospeccao.whatsapp.qrcode'))->assertForbidden();
    }

    public function test_clinica_e_visitante_nao_acessam(): void
    {
        ['user' => $usuario] = $this->criarClinica();

        $this->get(route('painel.prospeccao.whatsapp.show'))->assertRedirect(route('login'));

        $this->actingAs($usuario, 'web')
            ->getJson(route('painel.prospeccao.whatsapp.show'))
            ->assertUnauthorized();
    }

    public function test_cria_instancia_exclusiva_de_forma_idempotente(): void
    {
        $this->fakeEvolution();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.prospeccao.whatsapp.instancia'))
            ->assertOk()
            ->assertJsonPath('criada', true)
            ->assertJsonPath('configurado', true)
            ->assertJsonPath('instance', 'lj-prospeccao');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.prospeccao.whatsapp.instancia'))
            ->assertOk()
            ->assertJsonPath('criada', false);

        $this->assertSame(1, ProspectWhatsAppSetting::count());

        $setting = ProspectWhatsAppSetting::current();
        $this->assertSame('lj-prospeccao', $setting->instance_name);
        $this->assertSame('uuid-abc', $setting->instance_uuid);
        $this->assertSame('token-da-instancia', $setting->instance_token);

        $this->assertSame(1, $this->contarRequisicoes('/instance/create'));
        $this->assertSame(1, $this->contarRequisicoes('/instance/fetchInstances'));
    }

    public function test_adota_instancia_existente_sem_recriar(): void
    {
        Http::fake([
            '*instance/fetchInstances' => Http::response([
                ['name' => 'lj-prospeccao', 'connectionStatus' => 'open', 'token' => 'tok-existente'],
            ], 200),
            '*instance/create' => Http::response(['instance' => ['instanceName' => 'lj-prospeccao']], 200),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.prospeccao.whatsapp.instancia'))
            ->assertOk()
            ->assertJsonPath('criada', true)
            ->assertJsonPath('status', ProspectWhatsAppService::STATUS_CONNECTED);

        $this->assertSame(0, $this->contarRequisicoes('/instance/create'));
        $this->assertSame('tok-existente', ProspectWhatsAppSetting::current()->instance_token);
    }

    public function test_gera_qr_somente_sob_acao_e_nao_persiste_o_codigo(): void
    {
        $this->fakeEvolution();
        $this->setting();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('painel.prospeccao.whatsapp.show'))
            ->assertOk();

        $this->assertSame(0, $this->contarRequisicoes('/instance/connect/'));

        $resposta = $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.prospeccao.whatsapp.qrcode'))
            ->assertOk()
            ->assertJsonPath('qrcode', 'data:image/png;base64,QUJDRA==');

        $this->assertSame(1, $this->contarRequisicoes('/instance/connect/'));

        $linha = DB::connection('central')->table('prospect_whatsapp_settings')->first();
        $this->assertStringNotContainsString('data:image/png', (string) json_encode($linha));
        $this->assertSame(0, DB::connection('central')->table('prospect_interactions')->count());
    }

    public function test_atualiza_estado_sem_gerar_qr(): void
    {
        Http::fake([
            '*instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
        ]);
        $this->setting();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.prospeccao.whatsapp.estado'))
            ->assertOk()
            ->assertJsonPath('conectado', true)
            ->assertJsonPath('status', ProspectWhatsAppService::STATUS_CONNECTED);

        $this->assertSame(0, $this->contarRequisicoes('/instance/connect/'));
        $this->assertNotNull(ProspectWhatsAppSetting::current()->connected_at);
    }

    public function test_instancia_ja_conectada_nao_gera_novo_qr(): void
    {
        Http::fake([
            '*instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            '*instance/connect/*' => Http::response(['base64' => 'data:image/png;base64,QUJDRA=='], 200),
        ]);
        $this->setting(['status' => ProspectWhatsAppService::STATUS_CONNECTED]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.prospeccao.whatsapp.qrcode'))
            ->assertOk()
            ->assertJsonPath('conectado', true)
            ->assertJsonMissingPath('qrcode');

        $this->assertSame(0, $this->contarRequisicoes('/instance/connect/'));
    }

    public function test_erro_da_evolution_nao_vaza_credenciais(): void
    {
        Http::fake([
            '*instance/connectionState/*' => Http::response(['message' => 'Unauthorized'], 500),
        ]);
        $this->setting();

        $resposta = $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.prospeccao.whatsapp.estado'))
            ->assertStatus(502);

        $corpo = (string) json_encode($resposta->json());
        $this->assertStringNotContainsString('chave-admin-secreta', $corpo);
        $this->assertStringNotContainsString('token-da-instancia', $corpo);
        $this->assertStringNotContainsString('Unauthorized', $corpo);
    }

    public function test_sem_chave_administrativa_responde_503_sem_quebrar(): void
    {
        config(['services.evolution.admin_key' => '']);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.prospeccao.whatsapp.instancia'))
            ->assertStatus(503)
            ->assertJsonPath('status', ProspectWhatsAppService::STATUS_ERROR);
    }

    public function test_geracao_de_qr_tem_rate_limit(): void
    {
        $this->fakeEvolution();
        $this->setting();

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($this->admin, 'admin')
                ->postJson(route('painel.prospeccao.whatsapp.qrcode'))
                ->assertOk();
        }

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.prospeccao.whatsapp.qrcode'))
            ->assertStatus(429);
    }

    public function test_nao_toca_na_instancia_malheiro_nem_em_clinicas(): void
    {
        $this->fakeEvolution();

        $this->actingAs($this->admin, 'admin')->postJson(route('painel.prospeccao.whatsapp.instancia'))->assertOk();
        $this->actingAs($this->admin, 'admin')->postJson(route('painel.prospeccao.whatsapp.estado'))->assertOk();
        $this->actingAs($this->admin, 'admin')->postJson(route('painel.prospeccao.whatsapp.qrcode'))->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'malheiro'));

        $criacao = collect(Http::recorded())
            ->first(fn ($pair) => str_contains($pair[0]->url(), '/instance/create'));
        $this->assertNotNull($criacao);
        $this->assertSame('lj-prospeccao', $criacao[0]->data()['instanceName'] ?? null);
        $this->assertFalse($criacao[0]->data()['qrcode'] ?? true);
    }

    public function test_nao_dispara_nada_para_o_n8n(): void
    {
        $this->fakeEvolution();

        $this->actingAs($this->admin, 'admin')->postJson(route('painel.prospeccao.whatsapp.instancia'))->assertOk();
        $this->actingAs($this->admin, 'admin')->postJson(route('painel.prospeccao.whatsapp.qrcode'))->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'n8n'));
    }

    public function test_base_importada_permanece_integra(): void
    {
        $dir = database_path('prospeccao');

        if (! is_file($dir.'/odontologia_januaria.json') || ! is_file($dir.'/saude_januaria_ampliada.json')) {
            $this->markTestSkipped('Datasets de prospecção ausentes no ambiente.');
        }

        $importer = app(ProspectImporter::class);

        foreach (['odontologia_januaria', 'saude_januaria_ampliada'] as $arquivo) {
            $importer->import(json_decode((string) File::get($dir.'/'.$arquivo.'.json'), true));
        }

        $this->assertSame(110, Prospect::count());
        $this->assertSame(111, ProspectOrigin::count());
    }

    /**
     * Fakes padrão: sem instância existente, criação OK, estado `close` e um QR.
     */
    protected function fakeEvolution(): void
    {
        Http::fake([
            '*instance/fetchInstances' => Http::response([], 200),
            '*instance/create' => Http::response([
                'instance' => ['instanceName' => 'lj-prospeccao', 'instanceId' => 'uuid-abc', 'status' => 'close'],
                'hash' => 'token-da-instancia',
            ], 200),
            '*instance/connectionState/*' => Http::response(['instance' => ['state' => 'close']], 200),
            '*instance/connect/*' => Http::response(['base64' => 'data:image/png;base64,QUJDRA=='], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function setting(array $atributos = []): ProspectWhatsAppSetting
    {
        return ProspectWhatsAppSetting::query()->create(array_merge([
            'instance_name' => 'lj-prospeccao',
            'instance_uuid' => 'uuid-abc',
            'instance_token' => 'token-da-instancia',
            'status' => ProspectWhatsAppService::STATUS_DISCONNECTED,
        ], $atributos));
    }

    protected function contarRequisicoes(string $trecho): int
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), $trecho))
            ->count();
    }
}
