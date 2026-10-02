<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Planos comerciais pelo painel.
 *
 * O ponto sensível aqui é o par recurso + limite: o formulário chama o limite de
 * `limite_ai_messages_per_month` e o banco precisa guardar
 * `ai_messages_per_month`, que é o nome que `Plan::featureLimits()` e o job de
 * consumo leem. Um prefixo esquecido vira plano que não bloqueia nada.
 */
class PlatformPlanTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::factory()->create();
    }

    public function test_cria_plano_com_recursos_e_limites(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('painel.planos.store'), [
                ...$this->dadosDoPlano(),
                'features' => [
                    'ai_agent' => ['habilitado' => 1, 'limite_ai_messages_per_month' => 5000],
                    'whatsapp' => ['habilitado' => 1],
                ],
            ])
            ->assertRedirect(route('painel.planos.index'));

        $plano = Plan::query()->where('slug', 'pro')->firstOrFail();

        $this->assertTrue($plano->is_active);
        $this->assertTrue($plano->is_featured);
        $this->assertTrue($plano->hasFeature('whatsapp'));
        $this->assertFalse($plano->hasFeature('instagram'));

        // O `limite_` é do formulário: o que fica no banco é o nome puro do limite.
        $limites = $plano->featureLimits('ai_agent');

        $this->assertSame(['ai_messages_per_month'], array_keys($limites));
        $this->assertSame(5000.0, (float) $limites['ai_messages_per_month']);
        $this->assertDatabaseHas('plan_features', ['plan_id' => $plano->id, 'feature' => 'whatsapp']);
        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'plano.criado',
            'admin_user_id' => $this->admin->id,
        ]);
    }

    public function test_recurso_que_nao_existe_na_plataforma_e_recusado(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('painel.planos.store'), [
                ...$this->dadosDoPlano(),
                'features' => ['recurso_inexistente' => ['habilitado' => 1]],
            ])
            ->assertSessionHasErrors('features.recurso_inexistente');

        $this->assertDatabaseCount('plans', 0);
    }

    public function test_atualizar_plano_troca_o_conjunto_de_recursos(): void
    {
        $plano = $this->plano();
        $plano->features()->create(['feature' => 'whatsapp', 'limits' => null]);
        $plano->features()->create(['feature' => 'instagram', 'limits' => null]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('painel.planos.update', $plano), [
                ...$this->dadosDoPlano(),
                'name' => 'Starter Plus',
                'features' => ['whatsapp' => ['habilitado' => 1]],
            ])
            ->assertRedirect(route('painel.planos.index'));

        // Recurso desmarcado não pode deixar a linha antiga valendo.
        $this->assertDatabaseCount('plan_features', 1);
        $this->assertTrue($plano->fresh()->hasFeature('whatsapp'));
        $this->assertFalse($plano->fresh()->hasFeature('instagram'));
        $this->assertSame('Starter Plus', $plano->fresh()->name);
    }

    public function test_plano_com_assinatura_nao_e_excluido(): void
    {
        ['clinic' => $clinic] = $this->criarClinica();
        $plano = $this->plano();
        $clinic->subscriptions()->create([
            'plan_id' => $plano->id,
            'status' => 'active',
            'started_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('painel.planos.destroy', $plano))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plans', ['id' => $plano->id]);
    }

    public function test_exclui_plano_sem_assinatura(): void
    {
        $plano = $this->plano();

        $this->actingAs($this->admin, 'admin')
            ->delete(route('painel.planos.destroy', $plano))
            ->assertRedirect(route('painel.planos.index'));

        $this->assertDatabaseMissing('plans', ['id' => $plano->id]);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'plano.excluido']);
    }

    public function test_lista_planos_com_quantidade_de_assinaturas(): void
    {
        ['clinic' => $clinic] = $this->criarClinica();
        $plano = $this->plano();
        $plano->features()->create(['feature' => 'whatsapp', 'limits' => null]);

        Subscription::query()->create([
            'clinic_id' => $clinic->id,
            'plan_id' => $plano->id,
            'status' => 'active',
            'started_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.planos.index'))
            ->assertOk()
            ->assertSee($plano->name)
            ->assertSee('WhatsApp');
    }

    public function test_suporte_nao_altera_planos(): void
    {
        $suporte = AdminUser::factory()->support()->create();
        $plano = $this->plano();

        $this->actingAs($suporte, 'admin')
            ->post(route('painel.planos.store'), $this->dadosDoPlano())
            ->assertForbidden();

        $this->actingAs($suporte, 'admin')
            ->put(route('painel.planos.update', $plano), $this->dadosDoPlano())
            ->assertForbidden();

        $this->actingAs($suporte, 'admin')
            ->delete(route('painel.planos.destroy', $plano))
            ->assertForbidden();

        $this->assertDatabaseCount('plans', 1);
    }

    /**
     * @return array<string, mixed>
     */
    protected function dadosDoPlano(): array
    {
        return [
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Para clínica com equipe grande',
            'price_monthly' => 899,
            'trial_days' => 30,
            'sort_order' => 2,
            'is_active' => 1,
            'is_featured' => 1,
        ];
    }

    protected function plano(): Plan
    {
        return Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter-'.uniqid(),
            'price_monthly' => 149,
            'trial_days' => 14,
            'is_active' => true,
        ]);
    }
}
