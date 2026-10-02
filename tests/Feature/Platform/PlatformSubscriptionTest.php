<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Clinic;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Assinaturas pelo painel: troca de plano, renovação e estado.
 *
 * Duas regras regem estes testes. A troca de plano não edita a assinatura antiga,
 * ela a encerra e abre outra — é o que preserva "por que a fatura de março foi
 * 149 e a de abril 450". E mudar o estado da assinatura reflete na clínica, porque
 * é `clinics.status` que o `ResolveTenant` lê para liberar o painel.
 */
class PlatformSubscriptionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::factory()->create();
    }

    public function test_nova_assinatura_encerra_a_vigente_e_abre_outra(): void
    {
        ['clinic' => $clinic] = $this->criarClinica();
        $antigo = $this->assinatura($clinic, $this->plano('Starter'), 'active');

        $novo = $this->plano('Pro');

        $this->actingAs($this->admin, 'admin')
            ->post(route('painel.assinaturas.store', $clinic), ['plan_id' => $novo->id])
            ->assertRedirect(route('painel.clinicas.show', $clinic));

        $antigo->refresh();

        $this->assertSame('cancelled', $antigo->status);
        $this->assertNotNull($antigo->cancelled_at);

        $atual = Subscription::query()
            ->where('clinic_id', $clinic->id)
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertSame($novo->id, $atual->plan_id);
        $this->assertSame($clinic->id, $atual->clinic_id);
        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'assinatura.criada',
            'clinic_id' => $clinic->id,
        ]);
    }

    public function test_plano_inativo_nao_pode_ser_assinado(): void
    {
        ['clinic' => $clinic] = $this->criarClinica();
        $plano = $this->plano('Descontinuado');
        $plano->update(['is_active' => false]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('painel.assinaturas.store', $clinic), ['plan_id' => $plano->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_renovar_soma_um_mes_ao_vencimento_atual(): void
    {
        ['clinic' => $clinic] = $this->criarClinica();
        $assinatura = $this->assinatura($clinic, $this->plano('Starter'), 'active', now()->addDays(10));

        $this->actingAs($this->admin, 'admin')
            ->post(route('painel.assinaturas.renovar', $assinatura))
            ->assertRedirect();

        $vencimento = $assinatura->fresh()->current_period_end;

        $this->assertSame('active', $assinatura->fresh()->status);
        $this->assertSame(
            $assinatura->current_period_end->copy()->addMonth()->toDateString(),
            $vencimento->toDateString(),
        );
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assinatura.renovada']);
    }

    public function test_renovar_assinatura_vencida_volta_a_valer_por_um_mes(): void
    {
        ['clinic' => $clinic] = $this->criarClinica();
        $assinatura = $this->assinatura($clinic, $this->plano('Starter'), 'active', now()->subMonths(3));

        $this->actingAs($this->admin, 'admin')
            ->post(route('painel.assinaturas.renovar', $assinatura))
            ->assertRedirect();

        // Contar a partir de hoje, e não do vencimento antigo: o cliente pagou.
        $this->assertSame(
            now()->addMonth()->toDateString(),
            $assinatura->fresh()->current_period_end->toDateString(),
        );
    }

    public function test_suspender_assinatura_desliga_a_clinica(): void
    {
        ['clinic' => $clinic] = $this->criarClinica();
        $assinatura = $this->assinatura($clinic, $this->plano('Starter'), 'active');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('painel.assinaturas.status', $assinatura), [
                'status' => 'suspended',
            ])
            ->assertRedirect();

        $this->assertSame('suspended', $assinatura->fresh()->status);

        $clinica = $clinic->refresh();

        $this->assertSame('suspended', $clinica->status);
        $this->assertFalse($clinica->is_active);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'assinatura.status_alterado']);
    }

    public function test_lista_assinaturas_com_clinica_e_plano(): void
    {
        ['clinic' => $clinic] = $this->criarClinica('Clínica Aurora', 'aurora@teste.test');
        $plano = $this->plano('Starter');

        $this->assinatura($clinic, $plano, 'active');

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.assinaturas.index'))
            ->assertOk()
            ->assertSee('Clínica Aurora')
            ->assertSee('Starter');
    }

    public function test_suporte_nao_mexe_em_assinatura(): void
    {
        ['clinic' => $clinic] = $this->criarClinica();
        $assinatura = $this->assinatura($clinic, $this->plano('Starter'), 'active');
        $suporte = AdminUser::factory()->support()->create();

        $this->actingAs($suporte, 'admin')
            ->post(route('painel.assinaturas.store', $clinic), ['plan_id' => $assinatura->plan_id])
            ->assertForbidden();

        $this->actingAs($suporte, 'admin')
            ->post(route('painel.assinaturas.renovar', $assinatura))
            ->assertForbidden();

        $this->actingAs($suporte, 'admin')
            ->patch(route('painel.assinaturas.status', $assinatura), ['status' => 'suspended'])
            ->assertForbidden();

        $this->assertSame('active', $assinatura->fresh()->status);
    }

    protected function plano(string $name): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => str($name)->lower()->slug().'-'.uniqid(),
            'price_monthly' => 149,
            'trial_days' => 14,
            'is_active' => true,
        ]);
    }

    protected function assinatura(
        Clinic $clinic,
        Plan $plan,
        string $status,
        mixed $periodEnd = null,
    ): Subscription {
        return $clinic->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => $status,
            'started_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => $periodEnd ?? now()->addMonth(),
        ]);
    }
}
