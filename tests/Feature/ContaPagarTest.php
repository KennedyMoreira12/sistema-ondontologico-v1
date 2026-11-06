<?php

namespace Tests\Feature;

use App\Models\AccountsPayable;
use App\Models\FinancialCategory;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class ContaPagarTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_cadastra_conta_a_pagar(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create', 'financeiro.view');
        $fornecedor = Supplier::create(['nome' => 'Imobiliária Centro']);
        $categoria = FinancialCategory::query()->where('nome', 'Aluguel')->firstOrFail();

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.store'), [
                'supplier_id' => $fornecedor->id,
                'financeiro_categoria_id' => $categoria->id,
                'nome_conta' => 'Aluguel de outubro',
                'valor' => 1800,
                'data_vencimento' => '2026-10-10',
                'tipo' => 'aluguel',
                'tipo_documento' => 'boleto',
            ])
            ->assertRedirect();

        $this->assertTenantDatabaseHas('accounts_payable', [
            'supplier_id' => $fornecedor->id,
            'nome_conta' => 'Aluguel de outubro',
            'valor' => 1800,
            'status' => 'pendente',
        ]);
    }

    public function test_baixa_parcial_mantem_pendente_e_baixa_total_quita(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive', 'financeiro.view');
        $metodo = PaymentMethod::create(['name' => 'Pix', 'label' => 'Pix']);

        $conta = AccountsPayable::create([
            'nome_conta' => 'Energia',
            'valor' => 500,
            'data_vencimento' => '2026-10-20',
            'status' => 'pendente',
            'tipo' => 'energia',
            'tipo_documento' => 'boleto',
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), [
                'valor' => 200,
                'payment_method_id' => $metodo->id,
                'data' => '2026-10-15',
                'idempotency_key' => 'baixa-1',
            ])
            ->assertRedirect();

        $this->assertSame('pendente', $conta->refresh()->status);
        $this->assertSame(300.0, $conta->saldo);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), [
                'valor' => 300,
                'payment_method_id' => $metodo->id,
                'data' => '2026-10-16',
                'idempotency_key' => 'baixa-2',
            ])
            ->assertRedirect();

        $conta->refresh();

        $this->assertSame('pago', $conta->status);
        $this->assertSame(0.0, $conta->saldo);
        $this->assertNotNull($conta->data_pagamento);
        $this->assertTenantDatabaseCount('accounts_payable_pagamentos', 2);
    }

    public function test_recusa_baixa_acima_do_saldo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive');
        $metodo = PaymentMethod::create(['name' => 'Dinheiro', 'label' => 'Dinheiro']);

        $conta = AccountsPayable::create([
            'nome_conta' => 'Internet',
            'valor' => 100,
            'data_vencimento' => '2026-10-20',
            'status' => 'pendente',
            'tipo' => 'servico',
            'tipo_documento' => 'outros',
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), [
                'valor' => 150,
                'payment_method_id' => $metodo->id,
                'data' => '2026-10-15',
            ])
            ->assertSessionHasErrors('valor');

        $this->assertTenantDatabaseCount('accounts_payable_pagamentos', 0);
    }

    public function test_chave_de_idempotencia_nao_duplica_baixa(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive');

        $conta = AccountsPayable::create([
            'nome_conta' => 'Água',
            'valor' => 200,
            'data_vencimento' => '2026-10-20',
            'status' => 'pendente',
            'tipo' => 'agua',
            'tipo_documento' => 'outros',
        ]);

        $dados = [
            'valor' => 200,
            'data' => '2026-10-15',
            'idempotency_key' => 'mesma-chave',
        ];

        $this->actingAs($user)->post(route('financeiro.contas-pagar.baixar', $conta), $dados);
        $this->actingAs($user)->post(route('financeiro.contas-pagar.baixar', $conta), $dados);

        $this->assertTenantDatabaseCount('accounts_payable_pagamentos', 1);
        $this->assertSame('pago', $conta->refresh()->status);
    }

    public function test_atraso_e_calculado_e_nao_gravado(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.view');

        $conta = AccountsPayable::create([
            'nome_conta' => 'Conta vencida',
            'valor' => 90,
            'data_vencimento' => now()->subDays(3)->toDateString(),
            'status' => 'pendente',
            'tipo' => 'outros',
            'tipo_documento' => 'outros',
        ]);

        $this->assertTrue($conta->estaAtrasada());
        $this->assertSame('atrasado', $conta->statusEfetivo());

        $this->actingAs($user)
            ->get(route('financeiro.contas-pagar.index'))
            ->assertOk()
            ->assertSee('Conta vencida')
            ->assertSee('Atrasado');

        $this->assertSame('pendente', $conta->refresh()->status);
    }

    public function test_fornecedor_com_conta_nao_pode_ser_excluido(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.delete', 'financeiro.view');
        $fornecedor = Supplier::create(['nome' => 'Fornecedor com conta']);

        AccountsPayable::create([
            'supplier_id' => $fornecedor->id,
            'nome_conta' => 'Compra de material',
            'valor' => 120,
            'data_vencimento' => '2026-10-20',
            'status' => 'pendente',
            'tipo' => 'fornecedor',
            'tipo_documento' => 'nf',
        ]);

        $this->actingAs($user)
            ->delete(route('financeiro.fornecedores.destroy', $fornecedor))
            ->assertSessionHasErrors('fornecedor');

        $this->assertTenantDatabaseHas('suppliers', ['id' => $fornecedor->id]);
    }

    public function test_cadastra_fornecedor(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create');

        $this->actingAs($user)
            ->post(route('financeiro.fornecedores.store'), [
                'nome' => 'Distribuidora de Materiais',
                'documento' => '12.345.678/0001-90',
                'tipo_documento' => 'nf',
            ])
            ->assertRedirect(route('financeiro.fornecedores.index'));

        $this->assertTenantDatabaseHas('suppliers', [
            'nome' => 'Distribuidora de Materiais',
            'tipo_documento' => 'nf',
            'is_active' => true,
        ]);
    }
}
