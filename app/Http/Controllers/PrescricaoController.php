<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrescricaoStoreRequest;
use App\Models\Paciente;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Professional;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrescricaoController extends Controller
{
    public function index(Paciente $paciente): View
    {
        $this->authorize('prontuario.view');

        /**
         * Prescrição é documento de qualquer profissional habilitado da
         * clínica. O filtro antigo (`tipo = dentista`) deixava a fisioterapia
         * sem profissional na receita; agora vale o profissional logado e, se
         * ele não tiver cadastro, o primeiro ativo da clínica.
         */
        $profissional = Professional::query()
            ->where('user_id', auth()->id())
            ->first()
            ?? Professional::query()->where('is_active', true)->orderBy('nome')->first();

        $prescricoes = $paciente->prescricoes()->with('items')->latest('data')->latest('id')->get();

        return view('prescricoes.index', compact('paciente', 'prescricoes', 'profissional'));
    }

    public function store(PrescricaoStoreRequest $request, Paciente $paciente): RedirectResponse
    {
        $prescricao = Prescription::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => Professional::where('user_id', auth()->id())->first()?->id,
            'data' => $request->date('data') ?? today(),
            'observacoes' => $request->input('observacoes'),
        ]);

        foreach ($request->input('items', []) as $item) {
            if (empty($item['medicamento'])) {
                continue;
            }

            PrescriptionItem::create([
                'prescription_id' => $prescricao->id,
                'medicamento' => $item['medicamento'],
                'dose' => $item['dose'] ?? null,
                'frequencia' => $item['frequencia'] ?? null,
                'duracao' => $item['duracao'] ?? null,
                'observacao' => $item['observacao'] ?? null,
            ]);
        }

        AuditLogger::log(
            'criar_prescricao',
            'prescriptions',
            $prescricao->id,
            depois: ['data' => $prescricao->data?->toDateString()]
        );

        return back()->with('success', 'Prescrição registrada no prontuário.');
    }

    public function destroy(Paciente $paciente, Prescription $prescricao): RedirectResponse
    {
        $this->authorize('prontuario.create');

        AuditLogger::log('excluir_prescricao', 'prescriptions', $prescricao->id);

        $prescricao->delete();

        return back()->with('success', 'Prescrição excluída.');
    }
}
