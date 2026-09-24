<?php

namespace Tests\Feature;

use App\Models\AccountsReceivable;
use App\Models\Paciente;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissaoRelatorio(string ...$permissoes): User
    {
        $role = Role::create(['name' => 'relatorio_teste']);

        foreach ($permissoes as $permissao) {
            $role->permissions()->attach(Permission::create([
                'name' => $permissao,
                'module' => 'relatorios',
                'action' => $permissao,
            ])->id);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_usuario_sem_role_nao_acessa_relatorios(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('relatorios.index'))
            ->assertForbidden();
    }

    public function test_index_mostra_resumo_financeiro(): void
    {
        $user = $this->userComPermissaoRelatorio('relatorios.view');
        $paciente = Paciente::create(['nome' => 'Paciente Relatório']);

        AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => now()->addDay(),
            'valor' => 500.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        $this->actingAs($user)
            ->get(route('relatorios.index'))
            ->assertOk()
            ->assertSee('Relatórios')
            ->assertSee('500,00');
    }

    public function test_index_marca_produto_com_estoque_baixo(): void
    {
        $user = $this->userComPermissaoRelatorio('relatorios.view');

        Product::create([
            'nome' => 'Luva Baixo',
            'estoque' => 2,
            'estoque_minimo' => 5,
            'controlar_estoque' => true,
        ]);

        $this->actingAs($user)
            ->get(route('relatorios.index'))
            ->assertOk()
            ->assertSee('Luva Baixo');
    }
}
