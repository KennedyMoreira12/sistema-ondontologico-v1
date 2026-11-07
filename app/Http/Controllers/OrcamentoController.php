<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Paciente;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\TreatmentPlan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrcamentoController extends Controller
{
    private const STATUS_LABEL = [
        'rascunho' => 'Rascunho',
        'emitido' => 'Emitido',
        'aprovado' => 'Aprovado',
        'recusado' => 'Recusado',
        'cancelado' => 'Cancelado',
    ];

    private const STATUS_COR = [
        'rascunho' => '#6c757d',
        'emitido' => '#0d6efd',
        'aprovado' => '#198754',
        'recusado' => '#dc3545',
        'cancelado' => '#6c757d',
    ];

    public function index(Paciente $paciente): View
    {
        $this->authorize('orcamentos.view');

        $orcamentos = $paciente->orcamentos()
            ->with('profissional')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get();

        return view('orcamentos.index', compact('paciente', 'orcamentos'));
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $this->authorize('orcamentos.create');

        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'validade' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $profissional = Professional::where('user_id', auth()->id())->first();

        $orcamento = $paciente->orcamentos()->create([
            'titulo' => $validated['titulo'],
            'validade' => $validated['validade'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
            'profissional_id' => $profissional?->id,
            'desconto' => 0,
            'subtotal' => 0,
            'total' => 0,
        ]);

        AuditLogger::log('criar_orcamento', 'budgets', $orcamento->id, depois: $orcamento->only(['titulo', 'status', 'total']));

        return redirect()->route('pacientes.orcamentos.show', [$paciente, $orcamento])
            ->with('success', 'Orçamento criado.');
    }

    public function show(Paciente $paciente, Budget $orcamento): View
    {
        $this->authorize('orcamentos.view');

        abort_unless($orcamento->paciente_id === $paciente->id, 404);

        $orcamento->load(['profissional', 'items.procedure']);

        $procedimentos = Procedure::where('is_active', true)->orderBy('nome')->get();
        $planos = TreatmentPlan::where('paciente_id', $paciente->id)->orderByDesc('updated_at')->get();

        return view('orcamentos.show', compact('paciente', 'orcamento', 'procedimentos', 'planos'));
    }

    public function update(Request $request, Paciente $paciente, Budget $orcamento): RedirectResponse
    {
        $this->authorize('orcamentos.update');

        abort_unless($orcamento->paciente_id === $paciente->id, 404);

        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'validade' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $antes = $orcamento->only(['titulo', 'validade', 'observacoes', 'status']);

        $orcamento->update([
            'titulo' => $validated['titulo'],
            'validade' => $validated['validade'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
        ]);

        if ($request->filled('status') && array_key_exists($request->string('status')->toString(), self::STATUS_LABEL)) {
            $this->aplicarStatus($orcamento, $request->string('status')->toString());
        }

        AuditLogger::log('atualizar_orcamento', 'budgets', $orcamento->id, antes: $antes, depois: $orcamento->only(['titulo', 'validade', 'status']));

        return back()->with('success', 'Orçamento atualizado.');
    }

    public function itemStore(Request $request, Paciente $paciente, Budget $orcamento): RedirectResponse
    {
        $this->authorize('orcamentos.create');

        abort_unless($orcamento->paciente_id === $paciente->id, 404);

        $procedure = Procedure::findOrFail($request->integer('procedure_id'));

        $valorUnitario = $request->filled('valor_unitario')
            ? (float) $request->input('valor_unitario')
            : (float) $procedure->valor_padrao;

        $quantidade = $request->integer('quantidade', 1);
        $desconto = $request->filled('desconto') ? (float) $request->input('desconto') : 0;
        $valorTotal = round(($valorUnitario * $quantidade) - $desconto, 2);

        $item = $orcamento->items()->create([
            'procedure_id' => $procedure->id,
            'dente' => vertical_permite('odontograma') && $request->filled('dente')
                ? $request->integer('dente')
                : null,
            'descricao' => $request->filled('descricao') ? $request->string('descricao')->toString() : null,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'desconto' => $desconto,
            'valor_total' => max($valorTotal, 0),
        ]);

        AuditLogger::log('adicionar_item_orcamento', 'budget_items', $item->id, depois: [$item->procedure_id, 'dente' => $item->dente, 'valor_total' => $item->valor_total]);

        $this->recalcularTotal($orcamento);

        return back()->with('success', 'Procedimento adicionado ao orçamento.');
    }

    public function itemDestroy(Paciente $paciente, Budget $orcamento, BudgetItem $item): RedirectResponse
    {
        $this->authorize('orcamentos.delete');

        abort_unless($orcamento->paciente_id === $paciente->id, 404);
        abort_unless($item->budget_id === $orcamento->id, 404);

        AuditLogger::log('remover_item_orcamento', 'budget_items', $item->id, antes: $item->only(['procedure_id', 'dente', 'valor_total']));

        $item->delete();
        $this->recalcularTotal($orcamento);

        return back()->with('success', 'Item removido do orçamento.');
    }

    public function destroy(Paciente $paciente, Budget $orcamento): RedirectResponse
    {
        $this->authorize('orcamentos.delete');

        abort_unless($orcamento->paciente_id === $paciente->id, 404);

        AuditLogger::log('excluir_orcamento', 'budgets', $orcamento->id, antes: $orcamento->only(['titulo', 'status']));

        $orcamento->items()->delete();
        $orcamento->delete();

        return redirect()->route('pacientes.orcamentos.index', $paciente)->with('success', 'Orçamento excluído.');
    }

    private function aplicarStatus(Budget $orcamento, string $status): void
    {
        if ($orcamento->status === $status) {
            return;
        }

        $orcamento->update(['status' => $status]);
    }

    private function recalcularTotal(Budget $orcamento): void
    {
        $subtotal = (float) $orcamento->items()->sum('valor_total');
        $total = max(round($subtotal - (float) $orcamento->desconto, 2), 0);

        $orcamento->update(['subtotal' => $subtotal, 'total' => $total]);
    }
}
