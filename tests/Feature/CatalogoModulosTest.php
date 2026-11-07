<?php

namespace Tests\Feature;

use App\Support\Modulos\CatalogoModulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\Concerns\PreparaClinicaFisioterapia;
use Tests\TestCase;

/**
 * Catálogo de módulos e a tela padrão de "funcionalidade planejada".
 *
 * O menu da clínica, o link interno e a URL digitada têm que concordar: um
 * módulo que existe abre a tela dele; um módulo previsto para a vertical, mas
 * ainda não entregue, mostra a mesma mensagem para toda a clínica; um módulo
 * de outra vertical não abre.
 */
class CatalogoModulosTest extends TestCase
{
    use CreatesTenants, PreparaClinicaFisioterapia, RefreshDatabase;

    public function test_o_catalogo_declara_os_12_modulos_padronizados(): void
    {
        $catalogo = app(CatalogoModulos::class);

        $this->assertCount(12, CatalogoModulos::MODULOS_PADRONIZADOS);
        $this->assertCount(12, $catalogo->padronizados());

        foreach (CatalogoModulos::MODULOS_PADRONIZADOS as $slug) {
            $this->assertTrue($catalogo->existe($slug), "O módulo {$slug} deveria estar no catálogo.");
        }
    }

    public function test_todo_modulo_do_catalogo_tem_os_campos_esperados_e_rota_existente(): void
    {
        $rotas = collect(app('router')->getRoutes())->map->getName()->filter()->all();

        foreach (app(CatalogoModulos::class)->todos() as $slug => $modulo) {
            foreach (['titulo', 'icone', 'grupo', 'tipo', 'rota', 'padrao', 'vertical', 'situacao', 'descricao'] as $campo) {
                $this->assertArrayHasKey($campo, $modulo, "O módulo {$slug} está sem o campo {$campo}.");
            }

            $this->assertContains($modulo['rota'], $rotas, "A rota {$modulo['rota']} do módulo {$slug} não existe.");
            $this->assertSame(
                CatalogoModulos::VERTICAIS,
                array_keys($modulo['situacao']),
                "O módulo {$slug} precisa declarar a situação em todas as verticais."
            );
        }
    }

    public function test_modulo_planejado_mostra_a_mensagem_padronizada(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('dashboard.view');

        $this->actingAs($user)
            ->get(route('modulos.planejado', 'tratamentos'))
            ->assertOk()
            ->assertViewIs('modulos.planejado')
            ->assertSee('Funcionalidade planejada — ainda não disponível para uso.');
    }

    public function test_modulo_disponivel_redireciona_para_a_propria_tela(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('dashboard.view');

        $this->actingAs($user)
            ->get(route('modulos.planejado', 'financeiro'))
            ->assertRedirect(route('financeiro.index'));
    }

    public function test_modulo_de_outra_vertical_nao_abre(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('dashboard.view');

        $this->actingAs($user)
            ->get(route('modulos.planejado', 'odontograma'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning');
    }

    public function test_o_endereco_antigo_do_menu_continua_apontando_para_a_tela_certa(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard', ['modulo' => 'financeiro']))
            ->assertRedirect(route('financeiro.index'));
    }

    public function test_o_item_planejado_do_menu_aponta_para_a_tela_padronizada(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('modulos.planejado', 'tratamentos'), escape: false);
    }

    public function test_a_clinica_odontologica_continua_vendo_o_odontograma(): void
    {
        ['user' => $user] = $this->clinicaOdontologica();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Odontograma')
            // Na odontologia nada está planejado: o odontograma e os planos de
            // tratamento seguem com as telas que sempre tiveram.
            ->assertDontSee(route('modulos.planejado', 'odontograma'), escape: false)
            ->assertDontSee(route('modulos.planejado', 'tratamentos'), escape: false);
    }

    public function test_a_clinica_de_fisioterapia_nao_ve_o_odontograma(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Odontograma');
    }

    public function test_o_layout_tem_atalho_de_teclado_e_menu_acessivel(): void
    {
        ['user' => $user] = $this->clinicaOdontologica();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pular para o conteúdo')
            ->assertSee('aria-label="Menu principal"', escape: false)
            ->assertSee('aria-controls="scoSidebar"', escape: false)
            ->assertSee('aria-expanded="false"', escape: false)
            ->assertSee('id="scoConteudo"', escape: false);
    }

    public function test_o_item_ativo_do_menu_marca_a_pagina_atual(): void
    {
        $clinica = $this->clinicaOdontologica();
        $user = $this->usuarioComPermissao($clinica, 'pacientes.view');

        $this->actingAs($user)
            ->get(route('pacientes.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', escape: false);
    }
}
