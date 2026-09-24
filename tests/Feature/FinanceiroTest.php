<?php

namespace Tests\Feature;

use App\Models\AccountsReceivable;
use App\Models\Paciente;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceiroTest extends TestCase
{
    use RefreshDatabase;

    private function createRoleComPermissoes(array $permissoes): Role
    {
        $role = Role::create(['name' => 'financeiro_teste']);

        foreach ($permissoes as $permissao) {
            $permission = Permission::create([
                'name' => $permissao,
                'module' => 'financeiro',
                'action' => $permissao,
            ]);

            $role->permissions()->attach($permission->id);
        }

        return $role;
    }

    private function userComPermissaoFinanceiro(string ...$permissoes): User
    {
        $role = $this->createRoleComPermissoes($permissoes);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_usuario_sem_role_nao_acessa_financeiro(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('financeiro.index'))
            ->assertForbidden();
    }

    public function test_index_lista_contas_a_receber(): void
    {
        $user = $this->userComPermissaoFinanceiro('financeiro.view');

        $paciente = Paciente::create([
            'nome' => 'Paciente Teste Financeiro',
            'status' => 'ativo',
        ]);

        $paga = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-10-01',
            'valor' => 500,
            'valor_pago' => 500,
            'status' => 'pago',
        ]);

        $aberta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 800,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        $this->actingAs($user)
            ->get(route('financeiro.index'))
            ->assertOk()
            ->assertSee('Financeiro')
            ->assertSee($paciente->nome)
            ->assertSee('01/10/2026')
            ->assertSee('R$ 800,00');
    }

    public function test_registra_pagamento_e_atualiza_conta(): void
    {
        $user = $this->userComPermissaoFinanceiro('financeiro.receive');

        $paciente = Paciente::create([
            'nome' => 'Paciente Teste Financeiro',
            'status' => 'ativo',
        ]);

        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 800,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        $metodo = PaymentMethod::create(['name' => 'Dinheiro', 'label' => 'Dinheiro']);

        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 300,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $conta->refresh();

        $this->assertSame(300.0, (float) $conta->valor_pago);
        $this->assertSame('parcial', $conta->status);

        $this->assertDatabaseHas('payments', [
            'accounts_receivable_id' => $conta->id,
            'valor' => 300,
        ]);
    }

    public function test_rejeita_pagamento_maior_que_saldo(): void
    {
        $user = $this->userComPermissaoFinanceiro('financeiro.receive');

        $paciente = Paciente::create([
            'nome' => 'Paciente Teste Financeiro',
            'status' => 'ativo',
        ]);

        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 100,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        $metodo = PaymentMethod::create(['name' => 'Dinheiro', 'label' => 'Dinheiro']);

        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 999,
                'payment_method_id' => $metodo->id,
            ])
            ->assertSessionHasErrors('valor');

        $this->assertDatabaseCount('payments', 0);
    }
}
