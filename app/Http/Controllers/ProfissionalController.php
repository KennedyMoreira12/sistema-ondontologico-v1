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
    /**
     * Regras de validação do cadastro de profissional.
     *
     * O `cro` continua existindo e continua valendo para a odontologia. O
     * fisioterapeuta tem o registro no conselho em `registro_profissional`
     * (CREFITO), que é um campo NOVO: nada foi renomeado nem removido. Para
     * quem é fisioterapeuta o registro é obrigatório, porque uma ficha
     * profissional sem registro não deveria ser aceita.
     *
     * @return array<string, mixed>
     */
    private function regras(Request $request): array
    {
        $regras = [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'cro' => ['nullable', 'string', 'max:30'],
            'registro_profissional' => ['nullable', 'string', 'max:50'],
            'conselho_regional' => ['nullable', 'string', 'max:20'],
            'tipo' => ['required', 'string', 'in:'.implode(',', Professional::TIPOS)],
            'comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'specialties' => ['nullable', 'array'],
            'specialties.*' => ['integer', 'exists:tenant.specialties,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($request->input('tipo') === 'fisioterapeuta') {
            $regras['registro_profissional'] = ['required', 'string', 'max:50'];
        }

        return $regras;
    }

    public function index(Request $request): View
    {
        $this->authorize('profissionais.view');

        $profissionais = Professional::query()
            ->with('specialties')
            ->when($request->filled('busca'), function ($query, $busca) {
                $query->where(fn ($q) => $q
                    ->where('nome', 'like', "%{$busca}%")
                    ->orWhere('cro', 'like', "%{$busca}%")
                    ->orWhere('registro_profissional', 'like', "%{$busca}%"));
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

        $validated = $request->validate($this->regras($request));

        $professional = Professional::create([
            'clinica_id' => auth()->user()->clinica_id,
            'nome' => $validated['nome'],
            'cpf' => $validated['cpf'] ?? null,
            'cro' => $validated['cro'] ?? null,
            'registro_profissional' => $validated['registro_profissional'] ?? null,
            'conselho_regional' => $validated['conselho_regional'] ?? null,
            'tipo' => $validated['tipo'],
            'comissao' => $validated['comissao'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($validated['specialties'])) {
            $professional->specialties()->sync($validated['specialties']);
        }

        AuditLogger::log('criar', 'professionals', $professional->id, depois: $professional->only(['nome', 'cro', 'registro_profissional', 'tipo']));

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

        $validated = $request->validate($this->regras($request));

        $antes = $profissional->only(['nome', 'cro', 'registro_profissional', 'tipo', 'is_active']);

        $profissional->fill([
            'nome' => $validated['nome'],
            'cpf' => $validated['cpf'] ?? null,
            'cro' => $validated['cro'] ?? null,
            'registro_profissional' => $validated['registro_profissional'] ?? null,
            'conselho_regional' => $validated['conselho_regional'] ?? null,
            'tipo' => $validated['tipo'],
            'comissao' => $validated['comissao'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ])->save();

        if (array_key_exists('specialties', $validated)) {
            $profissional->specialties()->sync($validated['specialties'] ?? []);
        }

        AuditLogger::log('atualizar', 'professionals', $profissional->id, antes: $antes, depois: $profissional->only(['nome', 'cro', 'registro_profissional', 'tipo', 'is_active']));

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
