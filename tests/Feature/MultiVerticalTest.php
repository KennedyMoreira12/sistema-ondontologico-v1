<?php

namespace Tests\Feature;

use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Paciente;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Tenant\VerticalManager;
use App\Support\Modulos\CatalogoModulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Clínica com MÚLTIPLAS verticais habilitadas.
 *
 * A Clínica Daiane ativa fisioterapia, Pilates e depilação a laser ao mesmo
 * tempo. A Malheiro continua apenas odontológica. O que estes testes protegem:
 *
 * - a lista `verticals` vale mais que a vertical primária;
 * - módulo de QUALQUER vertical habilitada abre, e módulo de vertical não
 *   habilitada continua recusado;
 * - o menu e a tela de módulo planejado concordam com a decisão do servidor;
 * - uma clínica sem `verticals` continua com o comportamento antigo.
 */
class MultiVerticalTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    /**
     * Papel com exatamente as permissões pedidas, no banco da clínica atual.
     */
    private function papelCom(string ...$permissoes): Role
    {
        $role = Role::create(['name' => 'papel_multivirtual_'.uniqid()]);

        foreach ($permissoes as $permissao) {
            [$module, $action] = array_pad(explode('.', $permissao, 2), 2, $permissao);

            $role->permissions()->attach(Permission::create([
                'name' => $permissao,
                'module' => $module,
                'action' => $action,
            ])->id);
        }

        return $role;
    }

    /**
     * @param  array{clinic: Clinic, user: ?User, token: string}  $clinica
     */
    private function usuarioComPermissao(array $clinica, string ...$permissoes): User
    {
        $user = $clinica['user'];

        $user->assignRole($this->papelCom(...$permissoes));

        return $user->refresh();
    }

    /**
     * @param  list<string>  $verticals
     */
    private function clinicaDaiane(array $verticals = [
        VerticalManager::FISIOTERAPIA,
        VerticalManager::PILATES,
        VerticalManager::LASER,
    ]): array
    {
        $clinica = $this->criarClinica('CLINICA DAIANE TEIXEIRA');
        $clinica['clinic']->update(['verticals' => $verticals]);

        return $clinica;
    }

    public function test_catalogo_e_vertical_manager_conhecem_as_mesmas_verticais(): void
    {
        $this->assertSame(
            VerticalManager::VERTICAIS,
            CatalogoModulos::VERTICAIS,
            'As duas listas de verticais não podem divergir.',
        );
    }

    public function test_clinica_com_multiplas_verticais_enxerga_todas_elas(): void
    {
        $this->clinicaDaiane();

        $verticals = app(VerticalManager::class);

        $this->assertSame(
            [VerticalManager::FISIOTERAPIA, VerticalManager::PILATES, VerticalManager::LASER],
            $verticals->verticals(),
        );
        $this->assertTrue($verticals->isFisioterapia());
        $this->assertTrue($verticals->isPilates());
        $this->assertTrue($verticals->isLaser());
        $this->assertFalse($verticals->isOdontologia());

        $this->assertTrue($verticals->permite('avaliacao'));
        $this->assertTrue($verticals->permite('turmas_pilates'));
        $this->assertTrue($verticals->permite('regioes_laser'));
        $this->assertFalse($verticals->permite('odontograma'));
    }

    public function test_clinica_odontologica_nao_enxerga_pilates_nem_laser(): void
    {
        $this->criarClinica('Clínica Malheiro');

        $verticals = app(VerticalManager::class);

        $this->assertSame([VerticalManager::ODONTOLOGIA], $verticals->verticals());
        $this->assertTrue($verticals->isOdontologia());
        $this->assertFalse($verticals->isFisioterapia());
        $this->assertFalse($verticals->isPilates());
        $this->assertFalse($verticals->isLaser());

        $this->assertTrue($verticals->permite('odontograma'));
        $this->assertFalse($verticals->permite('turmas_pilates'));
        $this->assertFalse($verticals->permite('regioes_laser'));
        $this->assertFalse($verticals->permite('avaliacao'));
    }

    public function test_menu_da_daiane_mostra_fisioterapia_pilates_e_laser_e_esconde_odontograma(): void
    {
        $clinica = $this->clinicaDaiane();
        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Turmas de Pilates')
            ->assertSee('Regiões a Laser')
            ->assertSee('Pacotes de sessões')
            ->assertDontSee('Odontograma');
    }

    public function test_menu_da_malheiro_continua_sem_pilates_e_sem_laser(): void
    {
        $clinica = $this->criarClinica('Clínica Malheiro');
        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Odontograma')
            ->assertDontSee('Turmas de Pilates')
            ->assertDontSee('Regiões a Laser');
    }

    public function test_modulo_planejado_de_pilates_abre_para_a_daiane(): void
    {
        $clinica = $this->clinicaDaiane();
        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('modulos.planejado', 'turmas_pilates'))
            ->assertOk()
            ->assertSee('Turmas de Pilates')
            ->assertSee('Funcionalidade planejada');
    }

    public function test_modulo_planejado_de_laser_abre_para_a_daiane(): void
    {
        $clinica = $this->clinicaDaiane();
        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('modulos.planejado', 'regioes_laser'))
            ->assertOk()
            ->assertSee('Regiões a Laser')
            ->assertSee('Funcionalidade planejada');
    }

    public function test_modulo_planejado_de_pilates_e_recusado_para_a_malheiro(): void
    {
        $clinica = $this->criarClinica('Clínica Malheiro');
        $user = $this->usuarioComPermissao($clinica, 'dashboard.view');

        $this->actingAs($user)
            ->get(route('modulos.planejado', 'turmas_pilates'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning');
    }

    public function test_rota_de_fisioterapia_continua_aberta_na_clinica_com_multiplas_verticais(): void
    {
        $clinica = $this->clinicaDaiane();
        $user = $this->usuarioComPermissao($clinica, 'avaliacao.view');

        $paciente = Paciente::create(['nome' => 'Paciente da Daiane']);

        $this->actingAs($user)
            ->get(route('pacientes.avaliacoes.index', $paciente))
            ->assertOk();
    }

    public function test_odontograma_continua_recusado_na_clinica_com_multiplas_verticais(): void
    {
        $clinica = $this->clinicaDaiane();
        $user = $this->usuarioComPermissao($clinica, 'odontograma.view');

        $paciente = Paciente::create(['nome' => 'Paciente da Daiane']);

        $this->actingAs($user)
            ->get(route('pacientes.odontograma.index', $paciente))
            ->assertForbidden();
    }

    public function test_clinica_sem_verticals_cai_na_vertical_primaria(): void
    {
        $clinica = $this->criarClinica('Clínica de Fisioterapia');
        $clinica['clinic']->update([
            'vertical' => VerticalManager::FISIOTERAPIA,
            'verticals' => null,
        ]);

        $verticals = app(VerticalManager::class);

        $this->assertSame([VerticalManager::FISIOTERAPIA], $verticals->verticals());
        $this->assertTrue($verticals->isFisioterapia());
        $this->assertFalse($verticals->isOdontologia());
    }

    public function test_verticals_ignora_valor_desconhecido(): void
    {
        $this->clinicaDaiane([VerticalManager::PILATES, 'vertical_que_nao_existe']);

        $this->assertSame([VerticalManager::PILATES], app(VerticalManager::class)->verticals());

        // Só valor inválido: cai na vertical primária, nunca em lista vazia.
        $this->clinicaDaiane(['vertical_que_nao_existe']);

        $this->assertSame([VerticalManager::ODONTOLOGIA], app(VerticalManager::class)->verticals());
    }

    public function test_situacao_do_modulo_considera_todas_as_verticais_da_clinica(): void
    {
        $clinica = $this->clinicaDaiane();
        $catalogo = app(CatalogoModulos::class);

        // Módulo comum: disponível em qualquer vertical.
        $this->assertSame(CatalogoModulos::DISPONIVEL, $catalogo->situacaoParaClinica('pacientes', $clinica['clinic']));

        // Plano de tratamento: odontológico, planejado para as três verticais da Daiane.
        $this->assertSame(CatalogoModulos::PLANEJADO, $catalogo->situacaoParaClinica('tratamentos', $clinica['clinic']));

        // Módulo de fisioterapia: disponível porque a fisioterapia está habilitada.
        $this->assertSame(CatalogoModulos::DISPONIVEL, $catalogo->situacaoParaClinica('pacotes', $clinica['clinic']));

        // Pilates e laser ainda não têm tela: planejado para a Daiane.
        $this->assertSame(CatalogoModulos::PLANEJADO, $catalogo->situacaoParaClinica('turmas_pilates', $clinica['clinic']));
        $this->assertSame(CatalogoModulos::PLANEJADO, $catalogo->situacaoParaClinica('regioes_laser', $clinica['clinic']));
    }
}
