<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class EstoqueTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_usuario_sem_role_nao_acessa_estoque(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->get(route('estoque.index'))
            ->assertForbidden();
    }

    public function test_index_lista_produtos(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('estoque.view');

        Product::create(['nome' => 'Luva Látex']);

        $this->actingAs($user)
            ->get(route('estoque.index'))
            ->assertOk()
            ->assertSee('Luva Látex');
    }

    public function test_cria_produto(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('estoque.create');

        $this->actingAs($user)
            ->post(route('estoque.store'), [
                'nome' => 'Seringa Descarpack',
                'estoque' => '10',
                'estoque_minimo' => '5',
                'is_active' => '1',
            ])
            ->assertRedirect(route('estoque.index'));

        $this->assertTenantDatabaseHas('products', ['nome' => 'Seringa Descarpack']);
    }

    public function test_registra_entrada_e_saida_de_estoque(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('estoque.update');

        $produto = Product::create(['nome' => 'Anestésico', 'estoque' => 10]);

        $this->actingAs($user)
            ->post(route('estoque.movimento', $produto), [
                'tipo' => 'entrada',
                'quantidade' => 5,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTenantDatabaseHas('products', ['id' => $produto->id, 'estoque' => 15]);
        $this->assertTenantDatabaseHas('stock_movements', [
            'product_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 5,
            'saldo_anterior' => 10,
            'saldo_posterior' => 15,
        ]);

        $this->actingAs($user)
            ->post(route('estoque.movimento', $produto), [
                'tipo' => 'saida',
                'quantidade' => 3,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTenantDatabaseHas('products', ['id' => $produto->id, 'estoque' => 12]);
    }

    public function test_recusa_saida_maior_que_estoque_atual(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('estoque.update');

        $produto = Product::create(['nome' => 'Brocas', 'estoque' => 2]);

        $this->actingAs($user)
            ->from(route('estoque.index'))
            ->post(route('estoque.movimento', $produto), [
                'tipo' => 'saida',
                'quantidade' => 5,
            ])
            ->assertSessionHasErrors('quantidade');

        $this->assertTenantDatabaseCount('stock_movements', 0);
        $this->assertTenantDatabaseHas('products', ['id' => $produto->id, 'estoque' => 2]);
    }
}
