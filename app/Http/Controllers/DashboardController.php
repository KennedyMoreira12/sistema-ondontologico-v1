<?php

namespace App\Http\Controllers;

use App\Models\AccountsReceivable;
use App\Models\Appointment;
use App\Models\Paciente;
use App\Models\Product;
use App\Models\TreatmentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->filled('modulo')) {
            $perPaciente = ['prontuario', 'odontograma', 'tratamentos', 'orcamentos'];

            if (in_array($request->string('modulo')->value(), $perPaciente, true)) {
                return redirect()->route('pacientes.index');
            }

            if ($request->string('modulo')->value() === 'financeiro') {
                return redirect()->route('financeiro.index');
            }

            if ($request->string('modulo')->value() === 'convenios') {
                return redirect()->route('convenios.index');
            }

            if ($request->string('modulo')->value() === 'profissionais') {
                return redirect()->route('profissionais.index');
            }

            if ($request->string('modulo')->value() === 'procedimentos') {
                return redirect()->route('procedimentos.index');
            }

            if ($request->string('modulo')->value() === 'estoque') {
                return redirect()->route('estoque.index');
            }

            if ($request->string('modulo')->value() === 'relatorios') {
                return redirect()->route('relatorios.index');
            }

            if ($request->string('modulo')->value() === 'documentos') {
                return view('documentos.index');
            }

            if ($request->string('modulo')->value() === 'fiscal') {
                return redirect()->route('fiscal.index');
            }

            return view('em-desenvolvimento', ['modulo' => $request->string('modulo')]);
        }

        $user = auth()->user();

        $consultasHoje = Gate::allows('agenda.view')
            ? Appointment::whereDate('inicio', today())->with(['paciente', 'profissional', 'status'])->orderBy('inicio')->get()
            : collect();

        $pacientes = Gate::allows('pacientes.view') ? Paciente::count() : 0;

        $tratamentosEmAndamento = Gate::allows('tratamentos.view')
            ? TreatmentPlan::whereIn('status', ['aprovado', 'em_andamento'])->count()
            : 0;

        $contasReceber = Gate::allows('financeiro.view')
            ? AccountsReceivable::where('status', '!=', 'pago')
                ->whereColumn('valor', '>', 'valor_pago')
                ->get()
            : collect();

        $estoqueBaixo = Gate::allows('estoque.view')
            ? Product::where('controlar_estoque', true)->whereColumn('estoque', '<=', 'estoque_minimo')->count()
            : 0;

        return view('dashboard', compact(
            'consultasHoje',
            'pacientes',
            'tratamentosEmAndamento',
            'contasReceber',
            'estoqueBaixo'
        ));
    }
}
