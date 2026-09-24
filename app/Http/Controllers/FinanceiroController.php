<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinanceiroPaymentRequest;
use App\Models\AccountsReceivable;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinanceiroController extends Controller
{
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

        $totalAberto = AccountsReceivable::whereIn('status', ['aberta', 'parcial'])
            ->sum('valor');

        $formasPagamento = PaymentMethod::orderBy('label')->get();

        return view('financeiro.index', compact('contas', 'formasPagamento', 'totalAberto'));
    }

    public function receive(FinanceiroPaymentRequest $request, AccountsReceivable $conta): RedirectResponse
    {
        $this->authorize('financeiro.receive');

        $valor = (float) $request->input('valor');

        abort_unless($conta->status !== 'pago', 409);

        if ($valor > $conta->getSaldoAttribute()) {
            return back()->withErrors([
                'valor' => 'Valor informado maior que o saldo devedor (R$ '.number_format($conta->saldo, 2, ',', '.').').',
            ])->withInput();
        }

        $antes = $conta->only(['valor_pago', 'status']);

        $pagamento = Payment::create([
            'accounts_receivable_id' => $conta->id,
            'payment_method_id' => $request->integer('payment_method_id'),
            'user_id' => auth()->id(),
            'data' => $request->filled('data') ? $request->date('data') : now(),
            'valor' => $valor,
            'observacoes' => $request->filled('observacoes') ? $request->string('observacoes')->toString() : null,
        ]);

        $conta->increment('valor_pago', $valor);
        $conta->status = $conta->valor_pago >= $conta->valor ? 'pago' : 'parcial';
        $conta->save();

        AuditLogger::log('registrar_pagamento', 'payments', $pagamento->id, antes: $antes, depois: $conta->only(['valor_pago', 'status']));

        return back()->with('success', 'Pagamento de R$ '.number_format($valor, 2, ',', '.').' registrado. Saldo restante: R$ '.number_format($conta->saldo, 2, ',', '.').'.');
    }
}
