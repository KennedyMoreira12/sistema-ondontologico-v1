<?php

namespace App\Http\Controllers;

use App\Models\AccountsReceivable;
use App\Models\Appointment;
use App\Models\Paciente;
use App\Models\Product;
use App\Models\TreatmentPlan;
use App\Services\Modulos\ResolutorModulo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        // `?modulo=` é o endereço legado dos links do menu. Ele passa pelo
        // mesmo resolvedor de qualquer outro módulo, para que o link interno,
        // a URL digitada e o item do menu nunca discordem entre si.
        if ($request->filled('modulo')) {
            return app(ResolutorModulo::class)->abrir($request->string('modulo')->value());
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
