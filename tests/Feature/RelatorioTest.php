<?php

namespace Tests\Feature;

use App\Models\AccountsReceivable;
use App\Models\Paciente;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class RelatorioTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_usuario_sem_role_nao_acessa_relatorios(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->get(route('relatorios.index'))
            ->assertForbidden();
    }

    public function test_index_mostra_resumo_financeiro(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

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
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

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
