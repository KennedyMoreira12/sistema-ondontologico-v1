<?php

namespace App\Http\Controllers;

use App\Exceptions\OperacaoFinanceiraInvalida;
use App\Http\Requests\FinanceiroPaymentRequest;
use App\Models\AccountsPayable;
use App\Models\AccountsPayablePayment;
use App\Models\AccountsReceivable;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\AuditLogger;
use App\Services\Financeiro\RecebimentoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceiroController extends Controller
{
    public function __construct(private RecebimentoService $recebimentos) {}

    public function index(): View
    {
        $this->authorize('financeiro.view');

        $contas = AccountsReceivable::query()
            ->with(['paciente:id,nome'])
            ->when(request('busca'), fn ($query, $busca) => $query->whereHas(
                'paciente',
                fn ($paciente) => $paciente->where('nome', 'like', "%{$busca}%")
            ))
            ->when(request('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'aberta' THEN 0 WHEN status = 'parcial' THEN 1 ELSE 2 END")
            ->orderBy('vencimento')
            ->paginate(20)
            ->withQueryString();

        /*
         * O total em aberto desconta o que já foi recebido. Uma conta parcial
         * de R$ 500 com R$ 200 pagos ainda deve R$ 300; somar o valor cheio
         * faria o topo da tela contradizer o saldo de cada linha.
         */
        $totalAberto = (float) AccountsReceivable::whereIn('status', ['aberta', 'parcial'])
            ->sum(DB::raw('valor - valor_pago'));

        $formasPagamento = PaymentMethod::orderBy('label')->get();

        return view('financeiro.index', compact('contas', 'formasPagamento', 'totalAberto'));
    }

    public function receive(FinanceiroPaymentRequest $request, AccountsReceivable $conta): RedirectResponse
    {
        $this->authorize('financeiro.receive');

        $antes = $conta->only(['valor_pago', 'status']);

        try {
            $pagamento = $this->recebimentos->receber($conta, [
                'valor' => $request->input('valor'),
                'desconto' => $request->filled('desconto') ? $request->input('desconto') : null,
                'payment_method_id' => $request->integer('payment_method_id'),
                'insurance_plan_id' => $request->filled('insurance_plan_id') ? $request->integer('insurance_plan_id') : null,
                'data' => $request->filled('data') ? $request->date('data') : now(),
                'observacoes' => $request->filled('observacoes') ? $request->string('observacoes')->toString() : null,
            ]);
        } catch (OperacaoFinanceiraInvalida $excecao) {
            return back()->withErrors(['valor' => $excecao->getMessage()])->withInput();
        }

        $conta->refresh();

        AuditLogger::log(
            'registrar_pagamento',
            'payments',
            $pagamento->id,
            antes: $antes,
            depois: $conta->only(['valor_pago', 'status']),
        );

        return back()->with('success', 'Pagamento de R$ '.number_format((float) $pagamento->valor, 2, ',', '.').' registrado. Saldo restante: R$ '.number_format($conta->saldo, 2, ',', '.').'.');
    }

    /**
     * Fluxo de caixa do período: dinheiro que entrou (pagamentos) menos o que
     * saiu (baixas de contas a pagar). Não inclui conta em aberto, porque
     * previsão não é caixa.
     */
    public function fluxoCaixa(Request $request): View
    {
        $this->authorize('financeiro.view');

        $de = $request->filled('de') ? $request->date('de')->startOfDay() : now()->startOfMonth();
        $ate = $request->filled('ate') ? $request->date('ate')->endOfDay() : now()->endOfDay();

        /*
         * Datas invertidas (de > até) não podem produzir uma tela vazia sem
         * explicação: o período é normalizado para o intervalo que o usuário
         * digitou.
         */
        if ($de->greaterThan($ate)) {
            [$de, $ate] = [$ate->copy()->startOfDay(), $de->copy()->endOfDay()];
        }

        $recebimentos = Payment::query()
            ->with(['paciente:id,nome', 'paymentMethod:id,label'])
            ->whereBetween('data', [$de, $ate])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        $pagamentos = AccountsPayablePayment::query()
            ->with(['conta:id,nome_conta', 'paymentMethod:id,label'])
            ->whereBetween('data', [$de, $ate])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        $totalRecebido = (float) $recebimentos->sum('valor');
        $totalPago = (float) $pagamentos->sum('valor');

        $porForma = $recebimentos
            ->groupBy(fn (Payment $pagamento): string => $pagamento->paymentMethod?->label ?? 'Sem forma')
            ->map(fn ($grupo, string $forma): array => [
                'forma' => $forma,
                'quantidade' => $grupo->count(),
                'total' => (float) $grupo->sum('valor'),
            ])
            ->sortByDesc('total')
            ->values();

        $porMes = $recebimentos
            ->groupBy(fn (Payment $pagamento): string => $pagamento->data->format('Y-m'))
            ->map(fn ($grupo, string $mes): array => [
                'mes' => $mes,
                'entradas' => (float) $grupo->sum('valor'),
            ]);

        return view('financeiro.fluxo-caixa', [
            'de' => $de,
            'ate' => $ate,
            'recebimentos' => $recebimentos,
            'pagamentos' => $pagamentos,
            'totalRecebido' => $totalRecebido,
            'totalPago' => $totalPago,
            'saldo' => round($totalRecebido - $totalPago, 2),
            'porForma' => $porForma,
            'porMes' => $porMes,
            'devedores' => (float) AccountsReceivable::query()
                ->whereIn('status', ['aberta', 'parcial'])
                ->sum(DB::raw('valor - valor_pago')),
            'aPagar' => (float) AccountsPayable::query()->emAberto()->sum(DB::raw(AccountsPayable::expressaoSaldo())),
        ]);
    }
}
