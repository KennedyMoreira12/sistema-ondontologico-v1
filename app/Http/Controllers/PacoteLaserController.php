<?php

namespace App\Http\Controllers;

use App\Models\PacoteLaser;
use App\Models\RegiaoLaser;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pacotes-modelo de sessões de laser, organizados por região.
 *
 * O pacote é uma referência de venda (sessões, valor e validade). Ele não é
 * debitado automaticamente: quem mostra o que já foi feito é o histórico de
 * aplicações.
 */
class PacoteLaserController extends Controller
{
    public function index(): View
    {
        $this->authorize('regioes_laser.view');

        $pacotes = PacoteLaser::query()
            ->with('regiao')
            ->withCount('aplicacoes')
            ->orderBy('regiao_id')
            ->orderBy('nome')
            ->paginate(20);

        $regioes = RegiaoLaser::query()->orderBy('nome')->get();

        return view('laser.pacotes', compact('pacotes', 'regioes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('regioes_laser.create');

        $validated = $request->validate($this->regras($request));

        $pacote = PacoteLaser::create([
            'regiao_id' => $validated['regiao_id'],
            'nome' => $validated['nome'],
            'total_sessoes' => $validated['total_sessoes'],
            'valor' => $validated['valor'] ?? 0,
            'validade_dias' => $validated['validade_dias'] ?? null,
            'descricao' => $validated['descricao'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('criar_pacote_laser', 'laser_pacotes', $pacote->id, depois: $pacote->only([
            'regiao_id', 'nome', 'total_sessoes', 'valor', 'validade_dias', 'is_active',
        ]));

        return redirect()
            ->route('regioes_laser.pacotes.index')
            ->with('success', 'Pacote cadastrado.');
    }

    public function update(Request $request, PacoteLaser $pacote): RedirectResponse
    {
        $this->authorize('regioes_laser.update');

        $validated = $request->validate($this->regras($request, $pacote));

        $antes = $pacote->only(['regiao_id', 'nome', 'total_sessoes', 'valor', 'validade_dias', 'descricao', 'is_active']);

        $pacote->update([
            'regiao_id' => $validated['regiao_id'],
            'nome' => $validated['nome'],
            'total_sessoes' => $validated['total_sessoes'],
            'valor' => $validated['valor'] ?? 0,
            'validade_dias' => $validated['validade_dias'] ?? null,
            'descricao' => $validated['descricao'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLogger::log('atualizar_pacote_laser', 'laser_pacotes', $pacote->id, antes: $antes, depois: $pacote->only([
            'regiao_id', 'nome', 'total_sessoes', 'valor', 'validade_dias', 'descricao', 'is_active',
        ]));

        return redirect()
            ->route('regioes_laser.pacotes.index')
            ->with('success', 'Pacote atualizado.');
    }

    public function destroy(PacoteLaser $pacote): RedirectResponse
    {
        $this->authorize('regioes_laser.delete');

        if ($pacote->aplicacoes()->exists()) {
            return back()->with('error', 'Este pacote já tem aplicações no histórico. Desative-o em vez de excluir.');
        }

        $antes = $pacote->only(['nome', 'total_sessoes', 'is_active']);

        $pacote->delete();

        AuditLogger::log('excluir_pacote_laser', 'laser_pacotes', $pacote->id, antes: $antes);

        return redirect()
            ->route('regioes_laser.pacotes.index')
            ->with('success', 'Pacote excluído.');
    }

    /**
     * @return array<string, mixed>
     */
    private function regras(Request $request, ?PacoteLaser $pacote = null): array
    {
        return [
            'regiao_id' => ['required', 'integer', 'exists:tenant.laser_regioes,id'],
            'nome' => [
                'required', 'string', 'max:120',
                Rule::unique('tenant.laser_pacotes', 'nome')
                    ->where('regiao_id', $request->integer('regiao_id'))
                    ->ignore($pacote?->id),
            ],
            'total_sessoes' => ['required', 'integer', 'min:1', 'max:100'],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'validade_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
