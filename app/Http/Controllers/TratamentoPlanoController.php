<?php

namespace App\Http\Controllers;

use App\Http\Requests\TratamentoPlanoItemRequest;
use App\Http\Requests\TratamentoPlanoStatusRequest;
use App\Http\Requests\TratamentoPlanoStoreRequest;
use App\Http\Requests\TratamentoPlanoUpdateRequest;
use App\Models\Paciente;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TratamentoPlanoController extends Controller
{
    private const STATUS_LABEL = [
        'rascunho' => 'Rascunho',
        'aprovado' => 'Aprovado',
        'em_andamento' => 'Em andamento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
    ];

    private const STATUS_COR = [
        'rascunho' => '#6c757d',
        'aprovado' => '#0d6efd',
        'em_andamento' => '#ffc107',
        'concluido' => '#198754',
        'cancelado' => '#dc3545',
    ];

    public function index(Paciente $paciente): View
    {
        $this->authorize('tratamentos.view');

        $planos = $paciente->planosTratamento()
            ->withCount('items')
            ->orderByDesc('updated_at')
            ->get();

        return view('planos.index', compact('paciente', 'planos'));
    }

    public function store(TratamentoPlanoStoreRequest $request, Paciente $paciente): RedirectResponse
    {
        $profissional = Professional::where('user_id', auth()->id())->first();

        $plano = $paciente->planosTratamento()->create([
            ...$request->safe(['titulo', 'descricao', 'desconto']),
            'descricao' => $request->filled('descricao') ? $request->string('descricao')->toString() : null,
            'desconto' => $request->filled('desconto') ? (float) $request->string('desconto') : 0,
            'profissional_id' => $profissional?->id,
            'total' => 0,
        ]);

        AuditLogger::log('criar_plano', 'treatment_plans', $plano->id, depois: $plano->only(['titulo', 'status', 'total']));

        return redirect()->route('pacientes.planos.show', [$paciente, $plano])
            ->with('success', 'Plano de tratamento criado.');
    }

    public function show(Paciente $paciente, TreatmentPlan $plano): View
    {
        $this->authorize('tratamentos.view');

        abort_unless($plano->paciente_id === $paciente->id, 404);

        $plano->load(['profissional', 'items.procedure']);

        $procedimentos = Procedure::where('is_active', true)->orderBy('nome')->get();

        return view('planos.show', compact('paciente', 'plano', 'procedimentos'));
    }

    public function update(TratamentoPlanoUpdateRequest $request, Paciente $paciente, TreatmentPlan $plano): RedirectResponse
    {
        abort_unless($plano->paciente_id === $paciente->id, 404);

        $antes = $plano->only(['titulo', 'descricao', 'desconto', 'status']);

        $plano->update([
            'titulo' => $request->string('titulo')->toString(),
            'descricao' => $request->filled('descricao') ? $request->string('descricao')->toString() : null,
            'desconto' => $request->filled('desconto') ? (float) $request->string('desconto') : 0,
        ]);

        if ($request->filled('status')) {
            $this->aplicarStatus($plano, $request->string('status')->toString());
        }

        $this->recalcularTotal($plano);

        AuditLogger::log('atualizar_plano', 'treatment_plans', $plano->id, antes: $antes, depois: $plano->only(['titulo', 'status', 'total']));

        return back()->with('success', 'Plano atualizado.');
    }

    public function status(TratamentoPlanoStatusRequest $request, Paciente $paciente, TreatmentPlan $plano): RedirectResponse
    {
        abort_unless($plano->paciente_id === $paciente->id, 404);

        $antes = $plano->only(['status']);

        $this->aplicarStatus($plano, $request->string('status')->toString());

        AuditLogger::log('mudar_status_plano', 'treatment_plans', $plano->id, antes: $antes, depois: $plano->only(['status']));

        return back()->with('success', 'Status atualizado para '.self::STATUS_LABEL[$plano->status].'.');
    }

    public function destroy(Paciente $paciente, TreatmentPlan $plano): RedirectResponse
    {
        $this->authorize('tratamentos.delete');

        abort_unless($plano->paciente_id === $paciente->id, 404);

        AuditLogger::log('cancelar_plano', 'treatment_plans', $plano->id, antes: $plano->only(['titulo', 'status']));

        $plano->items()->delete();
        $plano->delete();

        return redirect()->route('pacientes.planos.index', $paciente)->with('success', 'Plano de tratamento excluÃ­do.');
    }

    public function itemStore(TratamentoPlanoItemRequest $request, Paciente $paciente, TreatmentPlan $plano): RedirectResponse
    {
        abort_unless($plano->paciente_id === $paciente->id, 404);

        $item = $this->montarItem($request, $plano);

        AuditLogger::log('adicionar_procedimento', 'treatment_plan_items', $item->id, depois: [$item->procedure_id, 'dente' => $item->dente, 'valor_total' => $item->valor_total]);

        $this->recalcularTotal($plano);

        return back()->with('success', 'Procedimento adicionado ao plano.');
    }

    public function itemUpdate(TratamentoPlanoItemRequest $request, Paciente $paciente, TreatmentPlan $plano, TreatmentPlanItem $item): RedirectResponse
    {
        abort_unless($plano->paciente_id === $paciente->id, 404);
        abort_unless($item->treatment_plan_id === $plano->id, 404);

        $antes = $item->only(['procedure_id', 'quantidade', 'valor_unitario', 'desconto', 'status']);

        $this->montarItem($request, $plano, $item);

        if ($request->filled('status')) {
            $item->update(['status' => $request->string('status')->toString()]);
        }

        $this->recalcularTotal($plano);

        AuditLogger::log('atualizar_item_plano', 'treatment_plan_items', $item->id, antes: $antes, depois: $item->only(['quantidade', 'valor_unitario', 'desconto', 'status']));

        return back()->with('success', 'Item do plano atualizado.');
    }

    public function itemStatus(Paciente $paciente, TreatmentPlan $plano, TreatmentPlanItem $item): RedirectResponse
    {
        $this->authorize('tratamentos.update');

        abort_unless($plano->paciente_id === $paciente->id, 404);
        abort_unless($item->treatment_plan_id === $plano->id, 404);

        $status = $item->status === 'executado' ? 'planejado' : 'executado';

        $item->update(['status' => $status]);

        AuditLogger::log('mudar_status_item', 'treatment_plan_items', $item->id, depois: ['status' => $status]);

        return back()->with('success', $status === 'executado' ? 'Procedimento marcado como executado.' : 'Procedimento voltou para planejado.');
    }

    public function itemDestroy(Paciente $paciente, TreatmentPlan $plano, TreatmentPlanItem $item): RedirectResponse
    {
        $this->authorize('tratamentos.delete');

        abort_unless($plano->paciente_id === $paciente->id, 404);
        abort_unless($item->treatment_plan_id === $plano->id, 404);

        AuditLogger::log('remover_item_plano', 'treatment_plan_items', $item->id, antes: $item->only(['procedure_id', 'dente', 'valor_total']));

        $item->delete();
        $this->recalcularTotal($plano);

        return back()->with('success', 'Item removido do plano.');
    }

    /**
     * Cria ou atualiza o item e devolve a instÃ¢ncia persistida.
     */
    private function montarItem(TratamentoPlanoItemRequest $request, TreatmentPlan $plano, ?TreatmentPlanItem $item = null): TreatmentPlanItem
    {
        $procedure = Procedure::findOrFail($request->integer('procedure_id'));

        $valorUnitario = $request->filled('valor_unitario')
            ? (float) $request->string('valor_unitario')
            : (float) $procedure->valor_padrao;

        $quantidade = $request->integer('quantidade', 1);
        $desconto = $request->filled('desconto') ? (float) $request->string('desconto') : 0;
        $valorTotal = round(($valorUnitario * $quantidade) - $desconto, 2);

        $dados = [
            'procedure_id' => $procedure->id,
            'dente' => $request->filled('dente') ? $request->integer('dente') : null,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'desconto' => $desconto,
            'valor_total' => max($valorTotal, 0),
            'observacao' => $request->filled('observacao') ? $request->string('observacao')->toString() : null,
        ];

        if ($item === null) {
            $item = $plano->items()->create($dados);
        } else {
            $item->update($dados);
        }

        return $item;
    }

    private function aplicarStatus(TreatmentPlan $plano, string $status): void
    {
        if ($plano->status === $status) {
            return;
        }

        $plano->update(['status' => $status]);

        if ($status === 'concluido') {
            $plano->items()->where('status', '!=', 'executado')->update(['status' => 'executado']);
        }
    }

    private function recalcularTotal(TreatmentPlan $plano): void
    {
        $somaItens = (float) $plano->items()->sum('valor_total');
        $total = max(round($somaItens - (float) $plano->desconto, 2), 0);

        $plano->update(['total' => $total]);
    }
}
