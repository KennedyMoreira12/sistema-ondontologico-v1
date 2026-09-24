<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_modulos_por_paciente_redirecionam_para_a_lista_de_pacientes(): void
    {
        $user = User::factory()->create();

        foreach (['odontograma', 'prontuario', 'tratamentos', 'orcamentos'] as $modulo) {
            $this
                ->actingAs($user)
                ->get(route('dashboard', ['modulo' => $modulo]))
                ->assertStatus(302)
                ->assertRedirect(route('pacientes.index'));
        }
    }

    public function test_modulos_em_desenvolvimento_mostram_o_placeholder(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['modulo' => 'configuracoes_avancadas']))
            ->assertOk()
            ->assertViewIs('em-desenvolvimento');
    }

    public function test_dashboard_sem_modulo_renderiza(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard');
    }
}
