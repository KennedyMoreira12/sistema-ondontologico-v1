<?php

namespace App\Http\Controllers;

use App\Models\RegiaoLaser;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo de regiões corporais da depilação a laser.
 *
 * A região é a base do pacote-modelo e do histórico de aplicações. Por isso a
 * exclusão é recusada quando já existe pacote ou aplicação vinculada: apagar a
 * região deixaria o histórico sem referência.
 */
class RegiaoLaserController extends Controller
{
    public function index(): View
    {
        $this->authorize('regioes_laser.view');

        $regioes = RegiaoLaser::query()
            ->withCount(['pacotes', 'aplicacoes'])
            ->orderBy('ordem')
            ->orderBy('nome')
            ->paginate(20);

        return view('laser.regioes', compact('regioes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('regioes_laser.create');

        $validated = $request->validate($this->regras());

        $regiao = RegiaoLaser::create([
            'nome' => $validated['nome'],
            'descricao' => $validated['descricao'] ?? null,
            'ordem' => $validated['ordem'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('criar_regiao_laser', 'laser_regioes', $regiao->id, depois: $regiao->only([
            'nome', 'ordem', 'is_active',
        ]));

        return redirect()
            ->route('regioes_laser.index')
            ->with('success', 'Região cadastrada.');
    }

    public function update(Request $request, RegiaoLaser $regiao): RedirectResponse
    {
        $this->authorize('regioes_laser.update');

        $validated = $request->validate($this->regras($regiao));

        $antes = $regiao->only(['nome', 'descricao', 'ordem', 'is_active']);

        $regiao->update([
            'nome' => $validated['nome'],
            'descricao' => $validated['descricao'] ?? null,
            'ordem' => $validated['ordem'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLogger::log('atualizar_regiao_laser', 'laser_regioes', $regiao->id, antes: $antes, depois: $regiao->only([
            'nome', 'descricao', 'ordem', 'is_active',
        ]));

        return redirect()
            ->route('regioes_laser.index')
            ->with('success', 'Região atualizada.');
    }

    public function destroy(RegiaoLaser $regiao): RedirectResponse
    {
        $this->authorize('regioes_laser.delete');

        if ($regiao->pacotes()->exists()) {
            return back()->with('error', 'Esta região tem pacotes vinculados. Desative-a em vez de excluir.');
        }

        if ($regiao->aplicacoes()->exists()) {
            return back()->with('error', 'Esta região já tem aplicações no histórico. Desative-a em vez de excluir.');
        }

        $antes = $regiao->only(['nome', 'is_active']);

        $regiao->delete();

        AuditLogger::log('excluir_regiao_laser', 'laser_regioes', $regiao->id, antes: $antes);

        return redirect()
            ->route('regioes_laser.index')
            ->with('success', 'Região excluída.');
    }

    /**
     * @return array<string, mixed>
     */
    private function regras(?RegiaoLaser $regiao = null): array
    {
        return [
            'nome' => [
                'required', 'string', 'max:120',
                Rule::unique('tenant.laser_regioes', 'nome')->ignore($regiao?->id),
            ],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
