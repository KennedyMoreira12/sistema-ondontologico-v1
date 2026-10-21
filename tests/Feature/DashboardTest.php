<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_modulos_por_paciente_redirecionam_para_a_lista_de_pacientes(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.view');

        foreach (['odontograma', 'prontuario', 'tratamentos', 'orcamentos'] as $modulo) {
            $this
                ->actingAs($user)
                ->get(route('dashboard', ['modulo' => $modulo]))
                ->assertStatus(302)
                ->assertRedirect(route('pacientes.index'));
        }
    }

    public function test_modulo_desconhecido_mostra_a_tela_padronizada(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.view');

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['modulo' => 'configuracoes_avancadas']))
            ->assertOk()
            ->assertViewIs('modulos.planejado')
            ->assertSee('Funcionalidade planejada — ainda não disponível para uso.');
    }

    public function test_dashboard_sem_modulo_renderiza(): void
    {
        [, $user] = $this->criarUsuarioComPermissao(
            'agenda.view',
            'pacientes.view',
            'tratamentos.view',
            'financeiro.view',
            'estoque.view',
        );

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard');
    }
}
