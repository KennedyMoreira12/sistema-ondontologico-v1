<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fornecedores que recebem as contas a pagar.
 *
 * Excluir só é permitido quando o fornecedor não tem conta vinculada:
 * apagar o cadastro levaria junto o histórico de vencimentos e baixas.
 */
class FornecedorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('financeiro.view');

        $fornecedores = Supplier::query()
            ->withCount(['contasPayable as contas_abertas_count' => fn ($query) => $query->where('status', 'pendente')])
            ->when($request->filled('busca'), fn ($query) => $query->where(function ($sub) use ($request): void {
                $busca = $request->string('busca')->toString();
                $sub->where('nome', 'like', "%{$busca}%")
                    ->orWhere('documento', 'like', "%{$busca}%")
                    ->orWhere('contato', 'like', "%{$busca}%");
            }))
            ->orderBy('nome')
            ->paginate(25)
            ->withQueryString();

        return view('financeiro.fornecedores.index', compact('fornecedores'));
    }

    public function create(): View
    {
        $this->authorize('financeiro.create');

        return view('financeiro.fornecedores.create');
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $fornecedor = Supplier::create($request->validated() + ['is_active' => true]);

        AuditLogger::log('criar_fornecedor', 'suppliers', $fornecedor->id, depois: $fornecedor->only(['nome', 'documento']));

        return redirect()->route('financeiro.fornecedores.index')->with('success', 'Fornecedor cadastrado.');
    }

    public function edit(Supplier $fornecedor): View
    {
        $this->authorize('financeiro.update');

        return view('financeiro.fornecedores.edit', compact('fornecedor'));
    }

    public function update(SupplierRequest $request, Supplier $fornecedor): RedirectResponse
    {
        $dados = $request->validated();
        $dados['is_active'] = $request->boolean('is_active');

        $fornecedor->update($dados);

        AuditLogger::log('atualizar_fornecedor', 'suppliers', $fornecedor->id, depois: $fornecedor->only(['nome', 'documento', 'is_active']));

        return redirect()->route('financeiro.fornecedores.index')->with('success', 'Fornecedor atualizado.');
    }

    public function destroy(Supplier $fornecedor): RedirectResponse
    {
        $this->authorize('financeiro.delete');

        if ($fornecedor->contasPayable()->exists()) {
            return back()->withErrors([
                'fornecedor' => 'Este fornecedor possui contas a pagar vinculadas. Inative-o em vez de excluir.',
            ]);
        }

        AuditLogger::log('excluir_fornecedor', 'suppliers', $fornecedor->id, antes: $fornecedor->only(['nome', 'documento']));

        $fornecedor->delete();

        return back()->with('success', 'Fornecedor excluído.');
    }
}
