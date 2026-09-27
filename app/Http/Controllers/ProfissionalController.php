<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use App\Models\Specialty;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfissionalController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('profissionais.view');

        $profissionais = Professional::query()
            ->with('specialties')
            ->when($request->filled('busca'), function ($query, $busca) {
                $query->where(fn ($q) => $q
                    ->where('nome', 'like', "%{$busca}%")
                    ->orWhere('cro', 'like', "%{$busca}%"));
            })
            ->when($request->filled('status'), function ($query, $status) {
                $query->where('is_active', $status === 'ativo');
            })
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        return view('profissionais.index', compact('profissionais'));
    }

    public function create(): View
    {
        $this->authorize('profissionais.create');

        $especialidades = Specialty::orderBy('name')->get();

        return view('profissionais.create', compact('especialidades'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('profissionais.create');

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'cro' => ['nullable', 'string', 'max:30'],
            'tipo' => ['required', 'string', 'in:dentista,auxiliar,recepcionista,outro'],
            'comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'specialties' => ['nullable', 'array'],
            'specialties.*' => ['integer', 'exists:tenant.specialties,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $professional = Professional::create([
            'clinica_id' => auth()->user()->clinica_id,
            'nome' => $validated['nome'],
            'cpf' => $validated['cpf'] ?? null,
            'cro' => $validated['cro'] ?? null,
            'tipo' => $validated['tipo'],
            'comissao' => $validated['comissao'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($validated['specialties'])) {
            $professional->specialties()->sync($validated['specialties']);
        }

        AuditLogger::log('criar', 'professionals', $professional->id, depois: $professional->only(['nome', 'cro', 'tipo']));

        return redirect()->route('profissionais.index')->with('success', 'Profissional cadastrado com sucesso.');
    }

    public function edit(Professional $profissional): View
    {
        $this->authorize('profissionais.update');

        $especialidades = Specialty::orderBy('name')->get();

        return view('profissionais.edit', compact('profissional', 'especialidades'));
    }

    public function update(Request $request, Professional $profissional): RedirectResponse
    {
        $this->authorize('profissionais.update');

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'cro' => ['nullable', 'string', 'max:30'],
            'tipo' => ['required', 'string', 'in:dentista,auxiliar,recepcionista,outro'],
            'comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'specialties' => ['nullable', 'array'],
            'specialties.*' => ['integer', 'exists:tenant.specialties,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $antes = $profissional->only(['nome', 'cro', 'tipo', 'is_active']);

        $profissional->fill([
            'nome' => $validated['nome'],
            'cpf' => $validated['cpf'] ?? null,
            'cro' => $validated['cro'] ?? null,
            'tipo' => $validated['tipo'],
            'comissao' => $validated['comissao'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ])->save();

        if (array_key_exists('specialties', $validated)) {
            $profissional->specialties()->sync($validated['specialties'] ?? []);
        }

        AuditLogger::log('atualizar', 'professionals', $profissional->id, antes: $antes, depois: $profissional->only(['nome', 'cro', 'tipo', 'is_active']));

        return redirect()->route('profissionais.index')->with('success', 'Profissional atualizado com sucesso.');
    }

    public function destroy(Professional $profissional): RedirectResponse
    {
        $this->authorize('profissionais.delete');

        $antes = ['nome' => $profissional->nome];

        $profissional->delete();

        AuditLogger::log('excluir', 'professionals', $profissional->id, antes: $antes);

        return redirect()->route('profissionais.index')->with('success', 'Profissional excluído com sucesso.');
    }
}
