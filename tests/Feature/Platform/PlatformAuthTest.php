<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Painel da plataforma (guard `admin`) pelo formulário único de login.
 *
 * A tela é uma só, o que não significa que a sessão é uma só: o guard continua
 * separado do `web`. O que precisa ser provado aqui é que o mesmo formulário
 * leva a pessoa ao lado certo do sistema e que nenhum dos dois guards escorrega
 * para o outro.
 */
class PlatformAuthTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_tela_de_login_mostra_o_acesso_ao_painel(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('painel.dashboard'), escape: false);
    }

    /**
     * O link do canto inferior esquerdo aponta para o painel, que cai na tela de
     * login quando não há sessão de admin — nunca em 404 nem em loop.
     */
    public function test_acesso_ao_painel_na_tela_de_login_leva_de_volta_ao_login(): void
    {
        $this->get(route('painel.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_url_antiga_do_login_do_painel_cai_na_tela_unica(): void
    {
        $this->get('/painel/login')->assertRedirect(route('login'));
    }

    public function test_admin_entra_com_a_senha_certa(): void
    {
        $admin = AdminUser::factory()->create();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertTrue(Auth::guard('web')->guest());
        $response->assertRedirect(route('painel.dashboard'));
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_senha_errada_nao_autentica(): void
    {
        $admin = AdminUser::factory()->create();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'senha-errada',
        ]);

        $this->assertGuest('admin');
    }

    /**
     * Usuário de clínica tem senha válida e é o dono do guard `web`. O mesmo
     * formulário que o aceita precisa deixá-lo longe do painel.
     */
    public function test_usuario_de_clinica_entra_pelo_lado_dele_e_nao_abre_o_painel(): void
    {
        $clinica = $this->criarClinicaTeste();
        $user = User::factory()->forClinic($clinica->id)->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertGuest('admin');
    }

    public function test_admin_desativado_nao_entra_mesmo_com_senha_certa(): void
    {
        $admin = AdminUser::factory()->inactive()->create();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertGuest('admin');
    }

    public function test_admin_desativado_no_meio_da_sessao_perde_o_acesso(): void
    {
        $admin = AdminUser::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('painel.dashboard'))
            ->assertOk();

        $admin->update(['is_active' => false]);

        $response = $this->actingAs($admin, 'admin')->get(route('painel.dashboard'));

        $this->assertGuest('admin');
        $response->assertRedirect(route('login'));
    }

    public function test_sem_sessao_toda_tela_do_painel_cai_no_login(): void
    {
        $this->get(route('painel.clinicas.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * A URL tentada antes do login é devolvida depois dele, desde que pertença
     * ao guard que autenticou. É o que faz o admin que caiu no login tentando
     * abrir `/painel/clinicas` continuar onde ia.
     */
    public function test_admin_volta_para_a_url_do_painel_que_tentava_abrir(): void
    {
        $admin = AdminUser::factory()->create();

        $this->get(route('painel.clinicas.index'))
            ->assertRedirect(route('login'));

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('painel.clinicas.index'));
    }

    /**
     * O inverso também é verdade: um usuário de clínica nunca é devolvido a uma
     * URL do painel. Sem isso o login viraria um laço — ele abre `/painel`, é
     * rejeitado pelo `auth:admin`, e volta ao login para sempre.
     */
    public function test_usuario_de_clinica_nao_volta_para_url_do_painel(): void
    {
        $clinica = $this->criarClinicaTeste();
        $user = User::factory()->forClinic($clinica->id)->create();

        $this->get(route('painel.dashboard'))
            ->assertRedirect(route('login'));

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Um usuário de clínica que digitar `/painel` não entra no painel nem fica
     * preso no login: o `auth:admin` o joga na tela única e o middleware
     * `guest` dessa tela o devolve para o dashboard que é dele.
     */
    public function test_sessao_de_clinica_nao_abre_o_painel(): void
    {
        $clinica = $this->criarClinicaTeste();
        $user = User::factory()->forClinic($clinica->id)->create();

        $this->actingAs($user)
            ->get(route('painel.dashboard'))
            ->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_sai_do_painel(): void
    {
        $admin = AdminUser::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post('/painel/logout');

        $this->assertGuest('admin');
        $response->assertRedirect(route('login'));
    }

    public function test_login_bloqueia_apos_cinco_tentativas(): void
    {
        $admin = AdminUser::factory()->create();

        foreach (range(1, 5) as $tentativa) {
            $this->post('/login', [
                'email' => $admin->email,
                'password' => 'senha-errada',
            ]);
        }

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }
}
