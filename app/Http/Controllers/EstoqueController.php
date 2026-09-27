<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EstoqueController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('estoque.view');

        $produtos = Product::query()
            ->with('categoria', 'supplier')
            ->when($request->filled('busca'), function ($query, $busca) {
                $query->where(fn ($q) => $q
                    ->where('nome', 'like', "%{$busca}%")
                    ->orWhere('codigo', 'like', "%{$busca}%"));
            })
            ->when($request->filled('categoria'), function ($query, $categoria) {
                $query->where('product_category_id', $categoria);
            })
            ->when($request->boolean('baixo'), function ($query) {
                $query->where('controlar_estoque', true)
                    ->whereColumn('estoque', '<=', 'estoque_minimo');
            })
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        $categorias = ProductCategory::orderBy('name')->get();

        return view('estoque.index', compact('produtos', 'categorias'));
    }

    public function create(): View
    {
        $this->authorize('estoque.create');

        $categorias = ProductCategory::orderBy('name')->get();
        $fornecedores = Supplier::orderBy('nome')->get();

        return view('estoque.create', compact('categorias', 'fornecedores'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('estoque.create');

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:50', 'unique:tenant.products,codigo'],
            'unidade' => ['nullable', 'string', 'max:20'],
            'product_category_id' => ['nullable', 'integer', 'exists:tenant.product_categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:tenant.suppliers,id'],
            'estoque' => ['nullable', 'numeric', 'min:0'],
            'estoque_minimo' => ['nullable', 'numeric', 'min:0'],
            'custo' => ['nullable', 'numeric', 'min:0'],
            'preco' => ['nullable', 'numeric', 'min:0'],
            'validade' => ['nullable', 'date'],
            'lote' => ['nullable', 'string', 'max:255'],
            'controlar_estoque' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product = Product::create([
            ...$validated,
            'estoque' => $validated['estoque'] ?? 0,
            'estoque_minimo' => $validated['estoque_minimo'] ?? 0,
            'custo' => $validated['custo'] ?? 0,
            'preco' => $validated['preco'] ?? 0,
            'controlar_estoque' => $request->boolean('controlar_estoque', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('criar', 'products', $product->id, depois: $product->only(['nome', 'codigo']));

        return redirect()->route('estoque.index')->with('success', 'Produto cadastrado com sucesso.');
    }

    public function edit(Product $produto): View
    {
        $this->authorize('estoque.update');

        $categorias = ProductCategory::orderBy('name')->get();
        $fornecedores = Supplier::orderBy('nome')->get();

        return view('estoque.edit', compact('produto', 'categorias', 'fornecedores'));
    }

    public function update(Request $request, Product $produto): RedirectResponse
    {
        $this->authorize('estoque.update');

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:50', 'unique:tenant.products,codigo,'.$produto->id],
            'unidade' => ['nullable', 'string', 'max:20'],
            'product_category_id' => ['nullable', 'integer', 'exists:tenant.product_categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:tenant.suppliers,id'],
            'estoque' => ['nullable', 'numeric', 'min:0'],
            'estoque_minimo' => ['nullable', 'numeric', 'min:0'],
            'custo' => ['nullable', 'numeric', 'min:0'],
            'preco' => ['nullable', 'numeric', 'min:0'],
            'validade' => ['nullable', 'date'],
            'lote' => ['nullable', 'string', 'max:255'],
            'controlar_estoque' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $antes = $produto->only(['nome', 'codigo', 'estoque', 'estoque_minimo', 'is_active']);

        $produto->fill($validated)->save();

        AuditLogger::log('atualizar', 'products', $produto->id, antes: $antes, depois: $produto->only(['nome', 'codigo', 'estoque', 'estoque_minimo', 'is_active']));

        return redirect()->route('estoque.index')->with('success', 'Produto atualizado com sucesso.');
    }

    public function destroy(Product $produto): RedirectResponse
    {
        $this->authorize('estoque.delete');

        AuditLogger::log('excluir', 'products', $produto->id, antes: ['nome' => $produto->nome]);

        $produto->delete();

        return redirect()->route('estoque.index')->with('success', 'Produto excluído com sucesso.');
    }

    public function movimento(Request $request, Product $produto): RedirectResponse
    {
        $this->authorize('estoque.update');

        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:entrada,saida'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $saldoAnterior = $produto->estoque;
        $quantidade = $validated['quantidade'];

        if ($validated['tipo'] === 'saida' && $quantidade > $saldoAnterior) {
            return back()->withErrors([
                'quantidade' => 'Quantidade maior que o estoque atual ('.$saldoAnterior.').',
            ])->withInput();
        }

        $saldoPosterior = $validated['tipo'] === 'entrada'
            ? $saldoAnterior + $quantidade
            : $saldoAnterior - $quantidade;

        $produto->update(['estoque' => $saldoPosterior]);

        StockMovement::create([
            'product_id' => $produto->id,
            'user_id' => auth()->id(),
            'tipo' => $validated['tipo'] === 'entrada' ? 'entrada' : 'saida',
            'quantidade' => $quantidade,
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'custo' => $produto->custo,
            'motivo' => $validated['motivo'] ?? null,
        ]);

        AuditLogger::log('movimentar_estoque', 'stock_movements', $produto->id, antes: ['estoque' => $saldoAnterior], depois: ['estoque' => $saldoPosterior]);

        return back()->with('success', 'Movimentação registrada com sucesso.');
    }
}
