<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Conexão do WhatsApp por clínica pelo painel da plataforma.
 *
 * Tudo aqui usa `Http::fake`: nenhuma chamada real à Evolution, nenhuma
 * instância tocada. O que os testes protegem é o contrato da tela — a clínica
 * vem do binding (não do corpo), o token de uma clínica nunca é usado por outra,
 * a instância conectada não é desconectada e nenhum segredo volta ao navegador.
 */
class PlatformClinicWhatsAppTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected string $tenantsDir;

    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantsDir = storage_path('framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'tenants-'.uniqid());

        Config::set('tenancy.tenants_directory', $this->tenantsDir);
        Config::set('services.evolution.url', 'http://evolution.test');

        $this->admin = AdminUser::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->apagarPastaDeBancosDeTeste($this->tenantsDir);

        parent::tearDown();
    }

    public function test_super_admin_ve_a_secao_whatsapp_da_clinica(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response($this->estado('open'), 200),
        ]);

        $clinica = $this->clinicaComEvolution('aurora', 'tok-aurora');

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.clinicas.show', $clinica))
            ->assertOk()
            ->assertSee('WhatsApp')
            ->assertSee('Gerar QR Code');

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('painel.clinicas.whatsapp.show', $clinica))
            ->assertOk()
            ->assertJson(['estado' => 'open', 'conectado' => true]);
    }

    public function test_status_apenas_le_o_estado_e_nao_gera_qr(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response($this->estado('close'), 200),
        ]);

        $clinica = $this->clinicaComEvolution('aurora', 'tok-aurora');

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('painel.clinicas.whatsapp.show', $clinica))
            ->assertOk()
            ->assertJson(['estado' => 'close', 'conectado' => false]);

        // Abrir a tela não pode disparar pareamento: o QR só vem no clique.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/instance/connect/'));
        Http::assertSentCount(1);
    }

    public function test_gera_qr_quando_a_clinica_esta_desconectada(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response($this->estado('close'), 200),
            'evolution.test/instance/connect/*' => Http::response([
                'pairingCode' => null,
                'code' => '2@abc',
                'base64' => 'data:image/png;base64,AAA',
                'count' => 1,
            ], 200),
        ]);

        $clinica = $this->clinicaComEvolution('aurora', 'tok-aurora');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.qrcode', $clinica), ['numero' => '5511999999999'])
            ->assertOk()
            ->assertJson(['estado' => 'connecting', 'qrcode' => 'data:image/png;base64,AAA']);

        // A instância e o token são os da própria clínica.
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/instance/connect/aurora')
            && $request->hasHeader('apikey', 'tok-aurora'));

        // O número informado é validado e guardado no cadastro da clínica.
        $this->assertSame('5511999999999', $clinica->fresh()->whatsapp);
    }

    public function test_clinica_conectada_nao_e_desconectada_ao_pedir_qr(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response($this->estado('open'), 200),
        ]);

        $clinica = $this->clinicaComEvolution('aurora', 'tok-aurora');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.qrcode', $clinica), [])
            ->assertOk()
            ->assertJson(['estado' => 'open', 'conectado' => true]);

        // Já conectada: nunca chama o connect (que dispararia outro pareamento).
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/instance/connect/'));
        Http::assertSentCount(1);
    }

    public function test_numero_invalido_e_recusado_sem_chamar_a_evolution(): void
    {
        Http::fake();

        $clinica = $this->clinicaComEvolution('aurora', 'tok-aurora');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.qrcode', $clinica), ['numero' => 'telefone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero');

        Http::assertNothingSent();
    }

    public function test_clinica_sem_instancia_orienta_o_provisionamento(): void
    {
        Http::fake();

        ['clinic' => $clinica] = $this->criarClinica('Clínica Sem Instância', 'sem@teste.test');

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('painel.clinicas.whatsapp.show', $clinica))
            ->assertOk()
            ->assertJson(['configurado' => false]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.qrcode', $clinica), [])
            ->assertStatus(409)
            ->assertJsonPath('mensagem', fn ($mensagem) => str_contains($mensagem, 'procedimento administrativo'));

        // Nem cria, nem consulta instância nenhuma.
        Http::assertNothingSent();
    }

    public function test_nova_clinica_recebe_a_secao_whatsapp(): void
    {
        Http::fake();

        ['clinic' => $clinica] = $this->criarClinica('Clínica Nova', 'nova@teste.test');

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.clinicas.show', $clinica))
            ->assertOk()
            ->assertSee('WhatsApp');
    }

    public function test_suporte_nao_gera_qr(): void
    {
        Http::fake();

        $clinica = $this->clinicaComEvolution('aurora', 'tok-aurora');
        $suporte = AdminUser::factory()->support()->create();

        $this->actingAs($suporte, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.qrcode', $clinica), [])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_visitante_nao_acessa_a_secao(): void
    {
        $clinica = $this->clinicaComEvolution('aurora', 'tok-aurora');

        $this->getJson(route('painel.clinicas.whatsapp.show', $clinica))
            ->assertUnauthorized();
    }

    public function test_uma_clinica_nao_recupera_o_qr_de_outra(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response($this->estado('close'), 200),
            'evolution.test/instance/connect/*' => Http::response([
                'base64' => 'data:image/png;base64,BBB',
                'count' => 1,
            ], 200),
        ]);

        $this->clinicaComEvolution('aurora', 'tok-aurora');
        $borealis = $this->clinicaComEvolution('borealis', 'tok-borealis');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.qrcode', $borealis), [])
            ->assertOk();

        // O QR de B usa a instância e o token de B — nunca os de A.
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/instance/connect/borealis')
            && $request->hasHeader('apikey', 'tok-borealis'));

        Http::assertNotSent(fn (Request $request) => $request->hasHeader('apikey', 'tok-aurora'));
        Http::assertSentCount(2);
    }

    public function test_erro_da_evolution_nao_vaza_segredo(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response($this->estado('close'), 200),
            'evolution.test/instance/connect/*' => Http::response([
                'error' => true,
                'message' => 'Unauthorized apikey tok-aurora',
            ], 200),
        ]);

        $clinica = $this->clinicaComEvolution('aurora', 'tok-aurora');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.qrcode', $clinica), [])
            ->assertStatus(502)
            ->assertJsonMissingPath('qrcode');

        $conteudo = $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.qrcode', $clinica), [])
            ->getContent();

        $this->assertStringNotContainsString('tok-aurora', $conteudo);
        $this->assertStringNotContainsString('apikey', $conteudo);
    }

    public function test_falha_de_rede_devolve_erro_generico(): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response($this->estado('close'), 200),
            'evolution.test/instance/connect/*' => Http::failedConnection('timeout'),
        ]);

        $clinica = $this->clinicaComEvolution('aurora', 'tok-aurora');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('painel.clinicas.whatsapp.qrcode', $clinica), [])
            ->assertStatus(502)
            ->assertJsonPath('mensagem', fn ($mensagem) => str_contains($mensagem, 'Evolution'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function estado(string $state): array
    {
        return ['instance' => ['instanceName' => 'x', 'state' => $state]];
    }

    protected function clinicaComEvolution(string $instancia, string $token): Clinic
    {
        ['clinic' => $clinica] = $this->criarClinica("Clínica {$instancia}", "{$instancia}@teste.test");

        $clinica->update([
            'evolution_instance' => $instancia,
            'evolution_token' => $token,
        ]);

        return $clinica->fresh();
    }
}
