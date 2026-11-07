<?php

namespace Tests\Feature;

use App\Models\Central\Clinic;
use App\Models\Paciente;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Tenant\VerticalManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * A vertical decide, no SERVIDOR, quais módulos a clínica enxerga.
 *
 * Odontologia (a clínica Malheiro) continua enxergando o odontograma.
 * Fisioterapia não enxerga — e não basta esconder o link: a URL digitada à
 * mão tem que ser recusada igual, porque quem decide é o registro central da
 * clínica, nunca o navegador.
 */
class VerticalTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    /**
     * Papel com exatamente as permissões pedidas, no banco da clínica atual.
     */
    private function papelCom(string ...$permissoes): Role
    {
        $role = Role::create(['name' => 'papel_vertical_'.uniqid()]);

        foreach ($permissoes as $permisso) {
            [$module, $action] = array_pad(explode('.', $permisso, 2), 2, $permisso);

            $role->permissions()->attach(Permission::create([
                'name' => $permisso,
                'module' => $module,
                'action' => $action,
            ])->id);
        }

        return $role;
    }

    /**
     * @param  array{Clinic, \App\Models\Central\User|null}  $clinica
     */
    private function usuarioComPermissao(array $clinica, string ...$permissoes): \App\Models\Central\User
    {
        $user = $clinica['user'];

        $user->assignRole($this->papelCom(...$permissoes));

        return $user->refresh();
    }

    public function test_clinica_odontologica_mantem_acesso_ao_odontograma(): void
    {
        $clinica = $this->criarClinica('Clínica Odontológica');
        $user = $this->usuarioComPermissao($clinica, 'odontograma.view');

        $paciente = Paciente::create(['nome' => 'Paciente Odontologia']);

        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente))
            ->assertOk();
    }

    public function test_clinica_de_fisioterapia_nao_abre_o_odontograma(): void
    {
        $clinica = $this->criarClinica('Clínica de Fisioterapia');
        $clinica['clinic']->update(['vertical' => VerticalManager::FISIOTERAPIA]);

        $user = $this->usuarioComPermissao($clinica, 'odontograma.view');

        $paciente = Paciente::create(['nome' => 'Paciente Fisioterapia']);

        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente))
            ->assertForbidden();
    }

    public function test_escrita_no_odontograma_tambem_e_recusada_na_fisioterapia(): void
    {
        $clinica = $this->criarClinica('Clínica de Fisioterapia');
        $clinica['clinic']->update(['vertical' => VerticalManager::FISIOTERAPIA]);

        $user = $this->usuarioComPermissao($clinica, 'odontograma.view', 'odontograma.update');

        $paciente = Paciente::create(['nome' => 'Paciente Fisioterapia']);

        $this->actingAs($user)
            ->putJson(route('pacientes.odontograma.tooth.update', [$paciente, 'dente' => 16]), [
                'status' => 'ausente',
            ])
            ->assertForbidden();
    }

    public function test_menu_da_clinica_de_fisioterapia_nao_mostra_odontograma(): void
    {
        $clinica = $this->criarClinica('Clínica de Fisioterapia');
        $clinica['clinic']->update(['vertical' => VerticalManager::FISIOTERAPIA]);

        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Odontograma');
    }

    public function test_menu_da_clinica_odontologica_mostra_odontograma(): void
    {
        $clinica = $this->criarClinica('Clínica Odontológica');
        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Odontograma');
    }

    public function test_modulo_odontograma_por_url_redireciona_na_fisioterapia(): void
    {
        $clinica = $this->criarClinica('Clínica de Fisioterapia');
        $clinica['clinic']->update(['vertical' => VerticalManager::FISIOTERAPIA]);

        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard', ['modulo' => 'odontograma']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning');
    }

    public function test_vertical_nao_vem_do_navegador(): void
    {
        $clinica = $this->criarClinica('Clínica Odontológica');
        $user = $this->usuarioComPermissao($clinica, 'odontograma.view');

        $paciente = Paciente::create(['nome' => 'Paciente Odontologia']);

        // O navegador tries to claim "sou clínica de fisioterapia": o servidor
        // ignora, porque a vertical é do registro central da clínica.
        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente).'?vertical=fisioterapia')
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente).'?clinica_id=999')
            ->assertOk();
    }

    public function test_clinica_odontologica_continua_odontologica_por_padrao(): void
    {
        $clinica = $this->criarClinica('Clínica sem vertical');

        $this->assertSame('odontologia', $clinica['clinic']->fresh()->vertical);
    }

    public function test_dados_de_uma_clinica_nao_vazam_para_a_outra(): void
    {
        $odontologia = $this->criarClinica('Clínica Odontológica');
        Paciente::create(['clinica_id' => $odontologia['clinic']->id, 'nome' => 'Somente Odontologia', 'status' => 'ativo']);

        $fisioterapia = $this->criarClinica('Clínica de Fisioterapia');
        $fisioterapia['clinic']->update(['vertical' => VerticalManager::FISIOTERAPIA]);

        $this->conectar($fisioterapia['clinic']);

        $this->assertSame(
            [],
            Paciente::pluck('nome')->all(),
            'Um paciente da clínica de odontologia apareceu na clínica de fisioterapia.',
        );
    }
}
