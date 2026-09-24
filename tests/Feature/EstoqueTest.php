<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstoqueTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissaoEstoque(string ...$permissoes): User
    {
        $role = Role::create(['name' => 'estoque_teste']);

        foreach ($permissoes as $permissao) {
            $role->permissions()->attach(Permission::create([
                'name' => $permissao,
                'module' => 'estoque',
                'action' => $permissao,
            ])->id);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_usuario_sem_role_nao_acessa_estoque(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('estoque.index'))
            ->assertForbidden();
    }

    public function test_index_lista_produtos(): void
    {
        Product::create(['nome' => 'Luva Látex']);

        $this->actingAs($this->userComPermissaoEstoque('estoque.view'))
            ->get(route('estoque.index'))
            ->assertOk()
            ->assertSee('Luva Látex');
    }

    public function test_cria_produto(): void
    {
        $user = $this->userComPermissaoEstoque('estoque.create');

        $this->actingAs($user)
            ->post(route('estoque.store'), [
                'nome' => 'Seringa Descarpack',
                'estoque' => '10',
                'estoque_minimo' => '5',
                'is_active' => '1',
            ])
            ->assertRedirect(route('estoque.index'));

        $this->assertDatabaseHas('products', ['nome' => 'Seringa Descarpack']);
    }

    public function test_registra_entrada_e_saida_de_estoque(): void
    {
        $user = $this->userComPermissaoEstoque('estoque.update');
        $produto = Product::create(['nome' => 'Anestésico', 'estoque' => 10]);

        $this->actingAs($user)
            ->post(route('estoque.movimento', $produto), [
                'tipo' => 'entrada',
                'quantidade' => 5,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['id' => $produto->id, 'estoque' => 15]);
        $this->assertDatabaseHas('stock_movements', [
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

        $this->assertDatabaseHas('products', ['id' => $produto->id, 'estoque' => 12]);
    }

    public function test_recusa_saida_maior_que_estoque_atual(): void
    {
        $user = $this->userComPermissaoEstoque('estoque.update');
        $produto = Product::create(['nome' => 'Brocas', 'estoque' => 2]);

        $this->actingAs($user)
            ->from(route('estoque.index'))
            ->post(route('estoque.movimento', $produto), [
                'tipo' => 'saida',
                'quantidade' => 5,
            ])
            ->assertSessionHasErrors('quantidade');

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('products', ['id' => $produto->id, 'estoque' => 2]);
    }
}
