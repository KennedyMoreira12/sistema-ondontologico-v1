<?php

namespace App\Http\Controllers;

use App\Models\AccountsReceivable;
use App\Models\Appointment;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RelatorioController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('relatorios.view');

        $de = $request->filled('de') ? $request->date('de') : now()->startOfMonth();
        $ate = $request->filled('ate') ? $request->date('ate') : now()->endOfDay();

        $resumoFinanceiro = [
            'a_receber' => (float) AccountsReceivable::where('status', '!=', 'pago')->sum('valor'),
            'em_aberto' => (float) AccountsReceivable::where('status', '!=', 'pago')->sum(DB::raw('valor - valor_pago')),
            'pago' => (float) AccountsReceivable::where('status', 'pago')->sum('valor'),
            'recebido_no_periodo' => (float) DB::connection('tenant')->table('payments')
                ->whereBetween('data', [$de->startOfDay(), $ate->endOfDay()])
                ->sum('valor'),
        ];

        $contas = AccountsReceivable::query()
            ->with('paciente')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->whereBetween('vencimento', [$de->startOfDay(), $ate->endOfDay()])
            ->orderBy('vencimento')
            ->get();

        $consultas = Appointment::query()
            ->with(['paciente', 'profissional', 'status'])
            ->when($request->filled('status_agenda'), fn ($q) => $q->whereHas('status', fn ($s) => $s->where('name', $request->string('status_agenda'))))
            ->whereBetween('inicio', [$de->startOfDay(), $ate->endOfDay()])
            ->orderBy('inicio')
            ->get();

        $resumoAgenda = [
            'total' => $consultas->count(),
            'atendidas' => $consultas->where('status.name', 'atendido')->count(),
            'canceladas' => $consultas->where('status.name', 'cancelado')->count(),
            'faltas' => $consultas->where('status.name', 'faltou')->count(),
        ];

        $produtosBaixo = Product::where('controlar_estoque', true)
            ->whereColumn('estoque', '<=', 'estoque_minimo')
            ->orderBy('estoque')
            ->get();

        $movimentacoes = StockMovement::query()
            ->with(['product', 'user'])
            ->whereBetween('created_at', [$de->startOfDay(), $ate->endOfDay()])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $resumoEstoque = [
            'produtos' => Product::count(),
            'baixo' => $produtosBaixo->count(),
            'entradas' => $movimentacoes->where('tipo', 'entrada')->sum('quantidade'),
            'saidas' => $movimentacoes->where('tipo', 'saida')->sum('quantidade'),
        ];

        return view('relatorios.index', compact(
            'de',
            'ate',
            'resumoFinanceiro',
            'contas',
            'consultas',
            'resumoAgenda',
            'produtosBaixo',
            'movimentacoes',
            'resumoEstoque',
        ));
    }
}
