<?php

namespace App\Http\Controllers;

use App\Http\Requests\OdontogramToothRequest;
use App\Models\OdontogramTooth;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\ToothCondition;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OdontogramaController extends Controller
{
    public function index(Paciente $paciente): View
    {
        $this->authorize('odontograma.view');

        $condicoes = ToothCondition::orderBy('is_default', 'desc')->orderBy('name')->get();
        $odontogram = $paciente->odontogramas()->firstOrCreate(
            ['status' => 'ativo'],
            ['titulo' => 'Odontograma principal']
        );
        $marcas = $odontogram->dentes()->with('toothCondition')->get()->keyBy('dente');

        return view('odontograma.index', compact('paciente', 'condicoes', 'odontogram', 'marcas'));
    }

    public function updateTooth(OdontogramToothRequest $request, Paciente $paciente, int $dente): RedirectResponse
    {
        $odontogram = $paciente->odontogramas()->firstOrCreate(
            ['status' => 'ativo'],
            ['titulo' => 'Odontograma principal']
        );

        $marca = OdontogramTooth::updateOrCreate(
            ['odontogram_id' => $odontogram->id, 'dente' => $request->integer('dente')],
            [
                'tooth_condition_id' => $request->integer('tooth_condition_id'),
                'observacao' => $request->input('observacao'),
                'profissional_id' => Professional::where('user_id', auth()->id())->first()?->id,
            ]
        );

        AuditLogger::log(
            'marcar_dente',
            'odontogram_teeth',
            $marca->id,
            depois: ['dente' => $request->integer('dente'), 'tooth_condition_id' => $request->integer('tooth_condition_id')]
        );

        return back()->with('success', 'Condição do dente registrada no odontograma.');
    }

    public function destroyTooth(Paciente $paciente, int $dente): RedirectResponse
    {
        $this->authorize('odontograma.update');

        $odontogram = $paciente->odontogramas()->firstOrCreate(
            ['status' => 'ativo'],
            ['titulo' => 'Odontograma principal']
        );

        $odontogram->dentes()->where('dente', $dente)->delete();

        AuditLogger::log('limpar_dente', 'odontogram_teeth', $odontogram->id, antes: ['dente' => $dente]);

        return back()->with('success', 'Marca removida — dente considerado hígido.');
    }
}
