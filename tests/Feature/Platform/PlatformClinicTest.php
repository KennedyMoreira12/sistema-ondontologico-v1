<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Gestão de clínicas pelo painel.
 *
 * O que importa aqui é que a tela use o mesmo provisionamento do artisan: banco
 * próprio, papéis semeados, admin e assinatura. Se o controller fizer o
 * onboarding na mão, a tela e o `tenant:create` passam a divergir sem ninguém
 * perceber.
 */
class PlatformClinicTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected string $tenantsDir;

    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantsDir = storage_path('framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'tenants-'.uniqid());

        Config::set('tenancy.tenants_directory', $this->tenantsDir);

        $this->admin = AdminUser::factory()->create();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tenantsDir.DIRECTORY_SEPARATOR.'*') ?: [] as $arquivo) {
            @unlink($arquivo);
        }

        @rmdir($this->tenantsDir);

        parent::tearDown();
    }

    public function test_lista_as_clinicas_com_plano_e_banco(): void
    {
        ['clinic' => $clinica] = $this->criarClinica('Clínica Aurora', 'aurora@teste.test');
        $clinica->subscriptions()->create([
            'plan_id' => $this->plano()->id,
            'status' => 'active',
            'started_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.clinicas.index'))
            ->assertOk()
            ->assertSee('Clínica Aurora')
            ->assertSee('Starter');
    }

    public function test_filtra_clinicas_por_nome(): void
    {
        $this->criarClinica('Clínica Aurora', 'aurora@teste.test');
        $this->criarClinica('Clínica Borealis', 'borealis@teste.test');

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.clinicas.index', ['busca' => 'Borealis']))
            ->assertOk()
            ->assertSee('Borealis')
            ->assertDontSee('Aurora');
    }

    public function test_cria_clinica_com_banco_proprio_admin_e_assinatura(): void
    {
        $plano = $this->plano();

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('painel.clinicas.store'), [
                'name' => 'Clínica Nova',
                'email' => 'nova@teste.test',
                'admin_name' => 'Dra Nova',
                'admin_email' => 'admin.nova@teste.test',
                'plan_id' => $plano->id,
            ]);

        $clinica = Clinic::query()->where('email', 'nova@teste.test')->firstOrFail();

        $response->assertRedirect(route('painel.clinicas.show', $clinica));
        $response->assertSessionHas('senha_provisoria');

        // Banco da clínica criado de verdade, com o schema de clínica.
        $arquivo = $clinica->tenantConnection->database_name;

        $this->assertFileExists($arquivo);
        $this->assertStringContainsString(
            'clinic_'.str_pad((string) $clinica->id, 6, '0', STR_PAD_LEFT),
            $arquivo,
        );

        // Usuário administrador no central, com o papel `admin` no banco da clínica.
        $usuario = $clinica->users()->where('email', 'admin.nova@teste.test')->firstOrFail();

        $this->assertTrue($usuario->is_active);

        // A conexão de teste ainda aponta para o banco da clínica de `criarClinica`
        // (quando houver); para olhar o banco NOVO é preciso reconectar nele.
        $this->apontarConexaoTenant($arquivo);

        $this->assertDatabaseHas('users', [
            'id' => $usuario->id,
            'clinic_id' => $clinica->id,
        ]);

        $this->assertTenantDatabaseHas('roles', ['name' => 'admin']);
        $this->assertTenantDatabaseHas('user_roles', ['user_id' => $usuario->id]);

        // Catálogo semeado: sem ele a clínica nasce sem nenhum procedimento.
        $this->assertGreaterThan(0, DB::connection('tenant')->table('procedures')->count());

        // Assinatura coerente com o plano escolhido.
        $assinatura = Subscription::query()->where('clinic_id', $clinica->id)->firstOrFail();

        $this->assertSame($plano->id, $assinatura->plan_id);
        $this->assertSame('trialing', $assinatura->status);
        $this->assertSame('trial', $clinica->status);

        // Tudo isso é registrado na auditoria da plataforma.
        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'clinica.criada',
            'clinic_id' => $clinica->id,
            'admin_user_id' => $this->admin->id,
        ]);
    }

    public function test_senha_do_admin_e_devolvida_apenas_na_hora(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('painel.clinicas.store'), [
                'name' => 'Clínica Senha',
                'email' => 'senha@teste.test',
                'admin_name' => 'Admin',
                'admin_email' => 'admin.senha@teste.test',
                'plan_id' => $this->plano()->id,
            ]);

        $clinica = Clinic::query()->where('email', 'senha@teste.test')->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.clinicas.show', $clinica))
            ->assertOk()
            ->assertSessionMissing('senha_provisoria');
    }

    public function test_suporte_nao_cria_clinica(): void
    {
        $suporte = AdminUser::factory()->support()->create();

        $this->actingAs($suporte, 'admin')
            ->post(route('painel.clinicas.store'), [
                'name' => 'Clínica Proibida',
                'email' => 'proibida@teste.test',
                'admin_name' => 'Admin',
                'admin_email' => 'admin.proibida@teste.test',
                'plan_id' => $this->plano()->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('clinics', 0);
    }

    public function test_atualiza_o_estado_da_clinica(): void
    {
        ['clinic' => $clinica] = $this->criarClinica();

        $this->actingAs($this->admin, 'admin')
            ->put(route('painel.clinicas.update', $clinica), [
                'name' => $clinica->name,
                'email' => $clinica->email,
                'timezone' => 'America/Sao_Paulo',
                'status' => 'suspended',
                'suspension_reason' => 'inadimplência',
            ])
            ->assertRedirect(route('painel.clinicas.show', $clinica));

        $clinica->refresh();

        $this->assertSame('suspended', $clinica->status);
        $this->assertFalse($clinica->is_active);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'clinica.atualizada']);
    }

    public function test_desligar_a_conexao_preserva_o_banco(): void
    {
        ['clinic' => $clinica] = $this->criarClinica();
        $arquivo = $clinica->tenantConnection->database_name;

        $this->actingAs($this->admin, 'admin')
            ->patch(route('painel.clinicas.conexao', $clinica))
            ->assertRedirect();

        $this->assertFalse($clinica->tenantConnection->fresh()->is_active);
        $this->assertFileExists($arquivo);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'clinica.conexao_desligada']);
    }

    public function test_excluir_clinica_preserva_o_banco_por_padrao(): void
    {
        ['clinic' => $clinica] = $this->criarClinica();
        $arquivo = $clinica->tenantConnection->database_name;

        $this->actingAs($this->admin, 'admin')
            ->delete(route('painel.clinicas.destroy', $clinica))
            ->assertRedirect(route('painel.clinicas.index'));

        $this->assertSoftDeleted('clinics', ['id' => $clinica->id]);
        $this->assertFalse($clinica->tenantConnection->fresh()->is_active);
        $this->assertFileExists($arquivo);
    }

    public function test_excluir_clinica_com_descarte_apaga_o_banco(): void
    {
        ['clinic' => $clinica] = $this->criarClinica();
        $arquivo = $clinica->tenantConnection->database_name;

        $this->actingAs($this->admin, 'admin')
            ->delete(route('painel.clinicas.destroy', $clinica), ['descartar_banco' => 1])
            ->assertRedirect(route('painel.clinicas.index'));

        $this->assertFileDoesNotExist($arquivo);

        // A clínica é arquivada, não apagada: a auditoria do painel ainda aponta
        // para ela, e é esse registro que diz por que o banco sumiu.
        $this->assertSoftDeleted('clinics', ['id' => $clinica->id]);
        $this->assertDatabaseMissing('tenant_connections', ['clinic_id' => $clinica->id]);
    }

    public function test_atualiza_o_schema_do_banco_da_clinica(): void
    {
        ['clinic' => $clinica] = $this->criarClinica();
        $clinica->tenantConnection->update(['last_migrated_at' => now()->subDays(30)]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('painel.clinicas.migrate', $clinica))
            ->assertRedirect();

        $this->assertTrue(
            $clinica->tenantConnection->fresh()->last_migrated_at->isToday(),
            'A migração pela tela precisa atualizar last_migrated_at.',
        );
    }

    public function test_gera_token_de_integracao_em_texto_purp(): void
    {
        ['clinic' => $clinica] = $this->criarClinica();

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('painel.clinicas.tokens.store', $clinica), ['name' => 'webhook-meta']);

        $response->assertSessionHas('token');

        // O valor em claro volta só na sessão do redirect; o banco guarda o SHA-256.
        $emTextoPuro = $this->app['session.store']->get('token');

        $token = $clinica->integrationTokens()->where('name', 'webhook-meta')->firstOrFail();

        $this->assertSame(64, strlen($token->token_hash));
        $this->assertSame(IntegrationToken::hashToken($emTextoPuro), $token->token_hash);
        $this->assertDatabaseMissing('integration_tokens', ['token_hash' => $emTextoPuro]);
    }

    public function test_operacoes_de_clinica_negam_para_suporte(): void
    {
        ['clinic' => $clinica] = $this->criarClinica();
        $suporte = AdminUser::factory()->support()->create();

        $this->actingAs($suporte, 'admin')
            ->delete(route('painel.clinicas.destroy', $clinica))
            ->assertForbidden();

        $this->assertDatabaseHas('clinics', ['id' => $clinica->id]);
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
