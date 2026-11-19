<?php

namespace Tests\Feature;

use App\Models\Central\AdminUser;
use App\Models\Central\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Migração individual de clínicas anteriores ao provisionamento automático.
 *
 * O que precisa ficar provado é a preservação: adotar nunca recria instância,
 * nunca troca token/número, nunca reaplica webhook e nunca mexe no workflow.
 */
class AdocaoClinicaWhatsAppLegadaTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.evolution.url', 'http://evolution.test');
        Config::set('services.evolution.admin_key', 'chave-administrativa');
        Config::set('services.evolution.webhook_secret', 'segredo-do-webhook');
        Config::set('services.evolution.webhook_url', 'https://app.test/api/evolution/webhook');
        Config::set('services.n8n.clinic_webhook_template', 'https://n8n.test/webhook/{instance}/atendimento');
    }

    public function test_dry_run_mostra_o_plano_sem_gravar_nada(): void
    {
        $this->fakeWebhookEEstado('close');
        $clinica = $this->clinicaLegada();

        $this->artisan('whatsapp:clinicas:adotar-legada', [
            '--clinic' => $clinica->id,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertNull($clinica->fresh()->whatsapp_legacy_adotado_at);
        $this->assertNull($clinica->fresh()->whatsapp_provisioned_at);

        Artisan::call('whatsapp:clinicas:adotar-legada', [
            '--clinic' => $clinica->id,
            '--dry-run' => true,
            '--json' => true,
        ]);

        $saida = (string) Artisan::output();
        $this->assertStringContainsString('clinica-legada', $saida);
        $this->assertStringContainsString('atendimento-legada', $saida);
        $this->assertStringContainsString('alteracoes_previstas', $saida);
        $this->assertStringNotContainsString('token-legado', $saida);
    }

    public function test_adocao_gravar_apenas_marcadores_e_preserva_instancia_token_e_roteamento(): void
    {
        $this->fakeWebhookEEstado('close');
        $clinica = $this->clinicaLegada();

        $this->artisan('whatsapp:clinicas:adotar-legada', [
            '--clinic' => $clinica->id,
            '--force' => true,
        ])->assertSuccessful();

        $clinica->refresh();

        $this->assertNotNull($clinica->whatsapp_legacy_adotado_at);
        $this->assertNotNull($clinica->whatsapp_provisioned_at);
        $this->assertNotNull($clinica->whatsapp_webhook_configured_at);
        $this->assertSame('clinica-legada', $clinica->evolution_instance);
        $this->assertSame('token-legado', $clinica->evolution_token);
        $this->assertSame('https://n8n.test/webhook/atendimento-legada', $clinica->n8n_webhook_url);
        $this->assertSame('close', $clinica->whatsapp_current_state);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/instance/create')
            || str_contains($request->url(), '/instance/connect/')
            || str_contains($request->url(), '/webhook/set/')
            || str_contains($request->url(), '/instance/logout/')
            || str_contains($request->url(), '/instance/delete/'));
    }

    public function test_adocao_e_idempotente(): void
    {
        $this->fakeWebhookEEstado('close');
        $clinica = $this->clinicaLegada();

        $this->artisan('whatsapp:clinicas:adotar-legada', [
            '--clinic' => $clinica->id,
            '--force' => true,
        ])->assertSuccessful();

        $marcador = $clinica->fresh()->whatsapp_legacy_adotado_at->toDateTimeString();
        $provisionado = $clinica->fresh()->whatsapp_provisioned_at->toDateTimeString();

        $this->artisan('whatsapp:clinicas:adotar-legada', [
            '--clinic' => $clinica->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame($marcador, $clinica->fresh()->whatsapp_legacy_adotado_at->toDateTimeString());
        $this->assertSame($provisionado, $clinica->fresh()->whatsapp_provisioned_at->toDateTimeString());
    }

    public function test_reverter_remove_apenas_os_marcadores_da_adocao(): void
    {
        $this->fakeWebhookEEstado('close');
        $clinica = $this->clinicaLegada();

        $this->artisan('whatsapp:clinicas:adotar-legada', [
            '--clinic' => $clinica->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->artisan('whatsapp:clinicas:adotar-legada', [
            '--clinic' => $clinica->id,
            '--reverter' => true,
            '--force' => true,
        ])->assertSuccessful();

        $clinica->refresh();

        $this->assertNull($clinica->whatsapp_legacy_adotado_at);
        $this->assertNull($clinica->whatsapp_provisioned_at);
        $this->assertSame('clinica-legada', $clinica->evolution_instance);
        $this->assertSame('token-legado', $clinica->evolution_token);
        $this->assertSame('https://n8n.test/webhook/atendimento-legada', $clinica->n8n_webhook_url);
    }

    public function test_adocao_sem_dados_suficientes_e_recusada(): void
    {
        Config::set('services.n8n.clinic_webhook_template', '');
        $this->fakeWebhookEEstado('close');
        $clinica = $this->clinicaLegada(['n8n_webhook_url' => null]);

        $this->artisan('whatsapp:clinicas:adotar-legada', [
            '--clinic' => $clinica->id,
            '--force' => true,
        ])->assertFailed();

        $this->assertNull($clinica->fresh()->whatsapp_legacy_adotado_at);
        $this->assertNull($clinica->fresh()->whatsapp_provisioned_at);
    }

    public function test_painel_diferencia_clinica_legada_da_provisionada(): void
    {
        Http::fake();
        $admin = AdminUser::factory()->create();
        $legada = $this->clinicaLegada();

        ['clinic' => $provisionada] = $this->criarClinica('Clínica Nova', 'nova@teste.test');
        $provisionada->update([
            'whatsapp_provisioned_at' => now(),
            'evolution_instance' => 'clinica-nova',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('painel.clinicas.show', $legada))
            ->assertOk()
            ->assertSee('Legada (não migrada)');

        $this->actingAs($admin, 'admin')
            ->get(route('painel.clinicas.show', $provisionada))
            ->assertOk()
            ->assertSee('Provisionado');
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function clinicaLegada(array $atributos = []): Clinic
    {
        ['clinic' => $clinica] = $this->criarClinica('Clínica Legada', 'legada@teste.test');

        $clinica->forceFill(array_merge([
            'evolution_instance' => 'clinica-legada',
            'evolution_token' => 'token-legado',
            'n8n_webhook_url' => 'https://n8n.test/webhook/atendimento-legada',
            'whatsapp' => '5511999999999',
        ], $atributos))->save();

        return $clinica->fresh();
    }

    protected function fakeWebhookEEstado(string $estado): void
    {
        Http::fake([
            'evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => $estado]], 200),
            'evolution.test/webhook/find/*' => Http::response([
                'webhook' => [
                    'enabled' => true,
                    'url' => 'https://app.test/api/evolution/webhook',
                    'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE'],
                ],
            ], 200),
        ]);
    }
}
