<?php

namespace App\Http\Controllers;

use App\Models\AccountsReceivable;
use App\Models\Appointment;
use App\Models\Paciente;
use App\Models\Product;
use App\Models\TreatmentPlan;
use App\Services\Tenant\VerticalManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->filled('modulo')) {
            $modulo = $request->string('modulo')->value();

            // A vertical decide no servidor: um módulo que não existe para a
            // clínica não abre nem por URL digitada.
            if (! app(VerticalManager::class)->permite($modulo)) {
                return redirect()
                    ->route('dashboard')
                    ->with('warning', 'Este módulo não está disponível para o perfil da sua clínica.');
            }

            $perPaciente = ['prontuario', 'odontograma', 'tratamentos', 'orcamentos', 'avaliacao', 'evolucao', 'documentos'];

            if (in_array($modulo, $perPaciente, true)) {
                return redirect()->route('pacientes.index');
            }

            if ($modulo === 'financeiro') {
                return redirect()->route('financeiro.index');
            }

            if ($modulo === 'convenios') {
                return redirect()->route('convenios.index');
            }

            if ($modulo === 'profissionais') {
                return redirect()->route('profissionais.index');
            }

            if ($modulo === 'procedimentos') {
                return redirect()->route('procedimentos.index');
            }

            if ($modulo === 'estoque') {
                return redirect()->route('estoque.index');
            }

            if ($modulo === 'relatorios') {
                return redirect()->route('relatorios.index');
            }

            return view('em-desenvolvimento', ['modulo' => $modulo]);
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
