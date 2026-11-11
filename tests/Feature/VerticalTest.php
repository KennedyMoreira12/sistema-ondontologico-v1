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
 * Como o sistema é vertical-agnóstico (atende clínicas de qualquer ramo),
 * todos os módulos do catálogo estão disponíveis para qualquer clínica.
 *
 * Estes testes garantem que:
 * - Qualquer clínica acessa qualquer módulo (sem restrição de vertical)
 * * O isolamento entre clínicas continua funcionando (banco por clínica)
 * - A vertical é apenas um campo informativo, não restritivo
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

    public function test_qualquer_clinica_tem_acesso_ao_odontograma(): void
    {
        $clinica = $this->criarClinica('Clínica Qualquer');
        $clinica['clinic']->update(['vertical' => 'qualquer']);
        $user = $this->usuarioComPermissao($clinica, 'odontograma.view');

        $paciente = Paciente::create(['nome' => 'Paciente Qualquer']);

        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente))
            ->assertOk();
    }

    public function test_qualquer_clinica_pode_escrever_no_odontograma(): void
    {
        $clinica = $this->criarClinica('Clínica Qualquer');
        $clinica['clinic']->update(['vertical' => 'qualquer']);
        $user = $this->usuarioComPermissao($clinica, 'odontograma.view', 'odontograma.update');

        $paciente = Paciente::create(['nome' => 'Paciente Qualquer']);

        $this->actingAs($user)
            ->putJson(route('pacientes.odontograma.tooth.update', [$paciente, 'dente' => 16]), [
                'status' => 'ausente',
            ])
            ->assertOk();
    }

    public function test_menu_de_qualquer_clinica_mostra_odontograma(): void
    {
        $clinica = $this->criarClinica('Clínica Qualquer');
        $clinica['clinic']->update(['vertical' => 'qualquer']);
        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Odontograma');
    }

    public function test_modulo_odontograma_por_url_acessivel_qualquer_clinica(): void
    {
        $clinica = $this->criarClinica('Clínica Qualquer');
        $clinica['clinic']->update(['vertical' => 'qualquer']);
        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard', ['modulo' => 'odontograma']))
            ->assertOk();
    }

    public function test_vertical_nao_vem_do_navegador(): void
    {
        $clinica = $this->criarClinica('Clínica Qualquer');
        $clinica['clinic']->update(['vertical' => 'qualquer']);
        $user = $this->usuarioComPermissao($clinica, 'odontograma.view');

        $paciente = Paciente::create(['nome' => 'Paciente Qualquer']);

        // O navegador tenta dizer "sou clínica de fisioterapia": o servidor
        // ignora, porque a vertical é do registro central da clínica.
        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente).'?vertical=fisioterapia')
            ->assertOk();

        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente).'?clinica_id=999')
            ->assertOk();
    }

    public function test_qualquer_valor_de_vertical_e_valido(): void
    {
        $clinica = $this->criarClinica('Clínica sem vertical');

        // Qualquer string não vazia é aceita como vertical/ramo.
        $clinica['clinic']->update(['vertical' => 'fisioterapia']);
        $this->assertSame('fisioterapia', $clinica['clinic']->fresh()->vertical);

        $clinica['clinic']->update(['vertical' => 'pilates']);
        $this->assertSame('pilates', $clinica['clinic']->fresh()->vertical);

        $clinica['clinic']->update(['vertical' => 'clinica_geral']);
        $this->assertSame('clinica_geral', $clinica['clinic']->fresh()->vertical);
    }

    public function test_dados_de_uma_clinica_nao_vazam_para_a_outra(): void
    {
        $clinicaA = $this->criarClinica('Clínica A');
        Paciente::create(['clinica_id' => $clinicaA['clinic']->id, 'nome' => 'Somente Clínica A', 'status' => 'ativo']);

        $clinicaB = $this->criarClinica('Clínica B');
        $clinicaB['clinic']->update(['vertical' => 'qualquer ramo']);

        $this->conectar($clinicaB['clinic']);

        $this->assertSame(
            [],
            Paciente::pluck('nome')->all(),
            'Um paciente da clínica A apareceu na clínica B.',
        );
    }
}
