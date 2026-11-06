<?php

namespace Tests\Feature;

use App\Models\AccountsReceivable;
use App\Models\Paciente;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class PagamentoTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_lista_pagamentos_e_marca_os_sem_vinculo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.view');

        Payment::create([
            'data' => '2026-10-10',
            'valor' => 250,
            'observacoes' => 'PIX da GISELE',
        ]);

        $this->actingAs($user)
            ->get(route('financeiro.pagamentos.index'))
            ->assertOk()
            ->assertSee('PIX da GISELE')
            ->assertSee('Revisar')
            ->assertSee('R$ 250,00');
    }

    public function test_filtra_somente_pagamentos_sem_paciente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.view');

        $paciente = Paciente::create(['nome' => 'Paciente Vinculado', 'status' => 'ativo']);

        Payment::create(['paciente_id' => $paciente->id, 'data' => '2026-10-11', 'valor' => 100]);
        Payment::create(['data' => '2026-10-12', 'valor' => 200, 'observacoes' => 'LANCAMENTO SEM DONO']);

        $this->actingAs($user)
            ->get(route('financeiro.pagamentos.index', ['vinculo' => 'sem']))
            ->assertOk()
            ->assertSee('LANCAMENTO SEM DONO')
            ->assertDontSee('Paciente Vinculado');
    }

    public function test_vincula_pagamento_importado_a_paciente_existente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.view', 'financeiro.update');

        $paciente = Paciente::create(['nome' => 'Gisele Alves', 'status' => 'ativo']);
        $pagamento = Payment::create([
            'data' => '2026-10-10',
            'valor' => 300,
            'observacoes' => 'Paciente: GISELE LVES',
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.pagamentos.vincular', $pagamento), ['paciente_id' => $paciente->id])
            ->assertRedirect(route('financeiro.pagamentos.revisar', $pagamento));

        $this->assertSame($paciente->id, $pagamento->refresh()->paciente_id);
        $this->assertTenantDatabaseHas('payments', ['id' => $pagamento->id, 'paciente_id' => $paciente->id]);
    }

    public function test_nao_sobrescreve_vinculo_existente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.update');

        $primeiro = Paciente::create(['nome' => 'Paciente Primeiro', 'status' => 'ativo']);
        $segundo = Paciente::create(['nome' => 'Paciente Segundo', 'status' => 'ativo']);
        $pagamento = Payment::create(['paciente_id' => $primeiro->id, 'data' => '2026-10-10', 'valor' => 50]);

        $this->actingAs($user)
            ->post(route('financeiro.pagamentos.vincular', $pagamento), ['paciente_id' => $segundo->id])
            ->assertSessionHasErrors('paciente_id');

        $this->assertSame($primeiro->id, $pagamento->refresh()->paciente_id);
    }

    public function test_lanca_pagamento_manual_criando_conta_quitada(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create');
        $paciente = Paciente::create(['nome' => 'Paciente Pagamento Manual', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'Pix', 'label' => 'Pix']);

        $this->actingAs($user)
            ->post(route('financeiro.pagamentos.store'), [
                'paciente_id' => $paciente->id,
                'descricao' => 'Pacote de Pilates',
                'servico' => 'Pilates',
                'valor' => 450,
                'payment_method_id' => $metodo->id,
                'data' => '2026-10-15',
            ])
            ->assertRedirect(route('financeiro.pagamentos.index'));

        $conta = AccountsReceivable::firstOrFail();

        $this->assertSame('pago', $conta->status);
        $this->assertSame(450.0, (float) $conta->valor_pago);
        $this->assertSame($paciente->id, $conta->paciente_id);

        $this->assertTenantDatabaseHas('payments', [
            'accounts_receivable_id' => $conta->id,
            'paciente_id' => $paciente->id,
            'valor' => 450,
            'servico' => 'Pilates',
            'origem' => 'manual',
        ]);
    }

    public function test_tela_de_duplicatas_mostra_grupo_sem_fundir_cadastros(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.view');

        Paciente::create(['nome' => 'Maria Silva', 'status' => 'ativo']);
        Paciente::create(['nome' => 'MARIA SILVA', 'status' => 'ativo']);

        $this->actingAs($user)
            ->get(route('financeiro.duplicatas.index'))
            ->assertOk()
            ->assertSee('MARIA SILVA')
            ->assertSee('Nada é fundido automaticamente');

        $this->assertTenantDatabaseCount('pacientes', 2);
    }

    public function test_usuario_sem_permissao_nao_acessa_pagamentos(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->get(route('financeiro.pagamentos.index'))
            ->assertForbidden();
    }
}
