<?php

namespace App\Http\Controllers;

use App\Models\InsuranceCompany;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConvenioController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('convenios.view');

        $operadoras = InsuranceCompany::query()
            ->withCount('plans')
            ->when($request->filled('busca'), function ($query, $busca) {
                $query->where(function ($q) use ($busca) {
                    $q->where('nome', 'like', "%{$busca}%")
                        ->orWhere('cnpj', 'like', "%{$busca}%");
                });
            })
            ->when($request->filled('status'), function ($query, $status) {
                $query->where('is_active', $status === 'ativo');
            })
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        return view('convenios.index', compact('operadoras'));
    }

    public function create(): View
    {
        $this->authorize('convenios.create');

        return view('convenios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('convenios.create');

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:20'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $operadora = InsuranceCompany::create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('criar', 'insurance_companies', $operadora->id, depois: $operadora->only(['nome', 'cnpj']));

        return redirect()->route('convenios.index')->with('success', 'Operadora cadastrada com sucesso.');
    }

    public function edit(InsuranceCompany $operadora): View
    {
        $this->authorize('convenios.update');

        return view('convenios.edit', compact('operadora'));
    }

    public function update(Request $request, InsuranceCompany $operadora): RedirectResponse
    {
        $this->authorize('convenios.update');

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:20'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $antes = $operadora->only(['nome', 'cnpj', 'is_active']);

        $operadora->fill($validated)->save();

        AuditLogger::log('atualizar', 'insurance_companies', $operadora->id, antes: $antes, depois: $operadora->only(['nome', 'cnpj', 'is_active']));

        return redirect()->route('convenios.index')->with('success', 'Operadora atualizada com sucesso.');
    }

    public function destroy(InsuranceCompany $operadora): RedirectResponse
    {
        $this->authorize('convenios.delete');

        $operadora->delete();

        AuditLogger::log('excluir', 'insurance_companies', $operadora->id, antes: ['nome' => $operadora->nome]);

        return redirect()->route('convenios.index')->with('success', 'Operadora excluída com sucesso.');
    }
}
