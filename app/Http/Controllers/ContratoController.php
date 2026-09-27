<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Paciente;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContratoController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('contratos.view');

        $contratos = Contract::with(['paciente', 'user'])
            ->when($request->filled('busca'), function ($query, $busca) {
                $query->whereHas('paciente', fn ($q) => $q->where('nome', 'like', "%{$busca}%"));
            })
            ->when($request->filled('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $statuses = Contract::query()->select('status')->distinct()->orderBy('status')->pluck('status');

        return view('contratos.index', compact('contratos', 'statuses'));
    }

    public function create(): View
    {
        $this->authorize('contratos.create');

        $pacientes = Paciente::where('status', 'ativo')->orderBy('nome')->get();

        return view('contratos.create', compact('pacientes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('contratos.create');

        $validated = $request->validate([
            'paciente_id' => ['required', 'exists:tenant.pacientes,id'],
            'tipo' => ['required', 'string', 'max:100'],
            'conteudo' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:pendente,assinado,cancelado'],
            'assinado_em' => ['nullable', 'date'],
        ]);

        $contrato = Contract::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        AuditLogger::log('criar', 'contracts', $contrato->id, depois: $contrato->only(['paciente_id', 'tipo', 'status']));

        return redirect()->route('contratos.index')->with('success', 'Contrato cadastrado com sucesso.');
    }

    public function edit(Contract $contrato): View
    {
        $this->authorize('contratos.update');

        $pacientes = Paciente::where('status', 'ativo')->orderBy('nome')->get();

        return view('contratos.edit', compact('contrato', 'pacientes'));
    }

    public function update(Request $request, Contract $contrato): RedirectResponse
    {
        $this->authorize('contratos.update');

        $validated = $request->validate([
            'paciente_id' => ['required', 'exists:tenant.pacientes,id'],
            'tipo' => ['required', 'string', 'max:100'],
            'conteudo' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:pendente,assinado,cancelado'],
            'assinado_em' => ['nullable', 'date'],
        ]);

        $antes = $contrato->only(['paciente_id', 'tipo', 'status']);

        $contrato->update($validated);

        AuditLogger::log('atualizar', 'contracts', $contrato->id, antes: $antes, depois: $contrato->only(['paciente_id', 'tipo', 'status']));

        return redirect()->route('contratos.index')->with('success', 'Contrato atualizado com sucesso.');
    }

    public function destroy(Contract $contrato): RedirectResponse
    {
        $this->authorize('contratos.delete');

        AuditLogger::log('excluir', 'contracts', $contrato->id, antes: ['tipo' => $contrato->tipo, 'status' => $contrato->status]);

        $contrato->delete();

        return redirect()->route('contratos.index')->with('success', 'Contrato excluído com sucesso.');
    }
}
