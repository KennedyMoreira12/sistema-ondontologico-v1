<?php

namespace App\Http\Controllers;

use App\Models\Procedure;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcedimentoController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('procedimentos.view');

        $procedimentos = Procedure::query()
            ->when($request->filled('busca'), function ($query, $busca) {
                $query->where(fn ($q) => $q
                    ->where('nome', 'like', "%{$busca}%")
                    ->orWhere('codigo', 'like', "%{$busca}%"));
            })
            ->when($request->filled('categoria'), function ($query, $categoria) {
                $query->where('categoria', $categoria);
            })
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        $categorias = Procedure::query()
            ->whereNotNull('categoria')
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        return view('procedimentos.index', compact('procedimentos', 'categorias'));
    }

    public function create(): View
    {
        $this->authorize('procedimentos.create');

        return view('procedimentos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('procedimentos.create');

        $validated = $request->validate([
            'codigo' => ['nullable', 'string', 'max:30', 'unique:procedures,codigo'],
            'nome' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'duracao_minutos' => ['nullable', 'integer', 'min:1'],
            'valor_padrao' => ['nullable', 'numeric', 'min:0'],
            'descricao' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $procedure = Procedure::create([
            ...$validated,
            'valor_padrao' => $validated['valor_padrao'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('criar', 'procedures', $procedure->id, depois: $procedure->only(['codigo', 'nome']));

        return redirect()->route('procedimentos.index')->with('success', 'Procedimento cadastrado com sucesso.');
    }

    public function edit(Procedure $procedimento): View
    {
        $this->authorize('procedimentos.update');

        return view('procedimentos.edit', compact('procedimento'));
    }

    public function update(Request $request, Procedure $procedimento): RedirectResponse
    {
        $this->authorize('procedimentos.update');

        $validated = $request->validate([
            'codigo' => ['nullable', 'string', 'max:30', 'unique:procedures,codigo,'.$procedimento->id],
            'nome' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'duracao_minutos' => ['nullable', 'integer', 'min:1'],
            'valor_padrao' => ['nullable', 'numeric', 'min:0'],
            'descricao' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $antes = $procedimento->only(['codigo', 'nome', 'valor_padrao', 'is_active']);

        $procedimento->fill([
            ...$validated,
            'valor_padrao' => $validated['valor_padrao'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ])->save();

        AuditLogger::log('atualizar', 'procedures', $procedimento->id, antes: $antes, depois: $procedimento->only(['codigo', 'nome', 'valor_padrao', 'is_active']));

        return redirect()->route('procedimentos.index')->with('success', 'Procedimento atualizado com sucesso.');
    }

    public function destroy(Procedure $procedimento): RedirectResponse
    {
        $this->authorize('procedimentos.delete');

        AuditLogger::log('excluir', 'procedures', $procedimento->id, antes: ['nome' => $procedimento->nome]);

        $procedimento->delete();

        return redirect()->route('procedimentos.index')->with('success', 'Procedimento excluído com sucesso.');
    }
}
