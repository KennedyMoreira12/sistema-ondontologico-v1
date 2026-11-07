<?php

namespace App\Http\Controllers;

use App\Exceptions\OperacaoFinanceiraInvalida;
use App\Http\Requests\AccountsPayablePaymentRequest;
use App\Http\Requests\AccountsPayableStoreRequest;
use App\Models\AccountsPayable;
use App\Models\FinancialCategory;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\Financeiro\ContasPagarService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Contas a pagar da clínica.
 *
 * O status guardado é só `pendente`, `pago` ou `cancelado`. "Atrasado" é
 * calculado pela data de vencimento a cada leitura; um status gravado
 * envelheceria sozinho e faria a listagem mentir no dia seguinte.
 */
class ContaPagarController extends Controller
{
    public function __construct(private ContasPagarService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('financeiro.view');

        $contas = AccountsPayable::query()
            ->with(['supplier:id,nome', 'categoria:id,nome'])
            ->withSum('pagamentos', 'valor')
            ->when($request->filled('busca'), function ($query) use ($request): void {
                $busca = $request->string('busca')->toString();

                $query->where(function ($sub) use ($busca): void {
                    $sub->where('nome_conta', 'like', "%{$busca}%")
                        ->orWhere('descricao', 'like', "%{$busca}%")
                        ->orWhereHas('supplier', fn ($fornecedor) => $fornecedor->where('nome', 'like', "%{$busca}%"));
                });
            })
            ->when($request->input('status') === AccountsPayable::PENDENTE, fn ($query) => $query
                ->where('status', AccountsPayable::PENDENTE)
                ->whereDate('data_vencimento', '>=', today()))
            ->when($request->input('status') === AccountsPayable::ATRASADO, fn ($query) => $query
                ->where('status', AccountsPayable::PENDENTE)
                ->whereDate('data_vencimento', '<', today()))
            ->when(in_array($request->input('status'), [AccountsPayable::PAGO, AccountsPayable::CANCELADO], true), fn ($query) => $query
                ->where('status', $request->input('status')))
            ->when($request->filled('tipo'), fn ($query) => $query->where('tipo', $request->input('tipo')))
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AccountsPayable::PENDENTE])
            ->orderBy('data_vencimento')
            ->paginate(25)
            ->withQueryString();

        /*
         * Os resumos usam o SALDO de cada conta (valor menos baixas), não o
         * valor cheio. Uma conta de R$ 1.000 com R$ 700 já pagos ainda tem
         * R$ 300 em aberto — somar o valor original inflaria o "em aberto" e
         * faria o atrasado parecer maior do que a dívida real.
         */
        $emAberto = $this->somarSaldo(AccountsPayable::query()->where('status', AccountsPayable::PENDENTE));
        $pago = AccountsPayable::query()->where('status', AccountsPayable::PAGO)->sum('valor');

        $atrasado = $this->somarSaldo(AccountsPayable::query()
            ->where('status', AccountsPayable::PENDENTE)
            ->whereDate('data_vencimento', '<', today()));

        $vencendo = $this->somarSaldo(AccountsPayable::query()
            ->where('status', AccountsPayable::PENDENTE)
            ->whereBetween('data_vencimento', [today(), today()->addDays(7)]));

        return view('financeiro.contas-pagar.index', [
            'contas' => $contas,
            'fornecedores' => Supplier::query()->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'resumo' => compact('emAberto', 'pago', 'atrasado', 'vencendo'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('financeiro.create');

        return view('financeiro.contas-pagar.create', [
            'fornecedores' => Supplier::query()->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'categorias' => FinancialCategory::query()->doTipo(FinancialCategory::DESPESA)->ativas()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(AccountsPayableStoreRequest $request): RedirectResponse
    {
        $conta = $this->service->criar($request->validated());

        AuditLogger::log('criar_conta_pagar', 'accounts_payable', $conta->id, depois: $conta->only(['nome_conta', 'valor', 'data_vencimento']));

        return redirect()
            ->route('financeiro.contas-pagar.show', $conta)
            ->with('success', 'Conta a pagar cadastrada.');
    }

    public function show(AccountsPayable $conta): View
    {
        $this->authorize('financeiro.view');

        $conta->load(['supplier', 'categoria', 'pagamentos.paymentMethod', 'pagamentos.user']);

        return view('financeiro.contas-pagar.show', [
            'conta' => $conta,
            'formasPagamento' => PaymentMethod::query()->orderBy('label')->get(),
        ]);
    }

    public function edit(AccountsPayable $conta): View
    {
        $this->authorize('financeiro.update');

        return view('financeiro.contas-pagar.edit', [
            'conta' => $conta,
            'fornecedores' => Supplier::query()->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'categorias' => FinancialCategory::query()->doTipo(FinancialCategory::DESPESA)->ativas()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function update(AccountsPayableStoreRequest $request, AccountsPayable $conta): RedirectResponse
    {
        if ($conta->status === AccountsPayable::CANCELADO) {
            return back()->withErrors(['nome_conta' => 'Uma conta cancelada não pode ser editada.'])->withInput();
        }

        $dados = $request->validated();
        $pago = round((float) $conta->pagamentos()->sum('valor'), 2);

        if (round((float) $dados['valor'], 2) < $pago) {
            return back()->withErrors([
                'valor' => 'O valor da conta não pode ficar abaixo do total já pago (R$ '.number_format($pago, 2, ',', '.').').',
            ])->withInput();
        }

        $antes = $conta->only(['nome_conta', 'valor', 'data_vencimento']);

        $conta->update($dados);

        AuditLogger::log('atualizar_conta_pagar', 'accounts_payable', $conta->id, antes: $antes, depois: $conta->only(['nome_conta', 'valor', 'data_vencimento']));

        return redirect()->route('financeiro.contas-pagar.show', $conta)->with('success', 'Conta atualizada.');
    }

    public function cancelar(AccountsPayable $conta): RedirectResponse
    {
        $this->authorize('financeiro.delete');

        try {
            $this->service->cancelar($conta);
        } catch (OperacaoFinanceiraInvalida $excecao) {
            return back()->withErrors(['status' => $excecao->getMessage()]);
        }

        AuditLogger::log('cancelar_conta_pagar', 'accounts_payable', $conta->id, antes: ['status' => AccountsPayable::PENDENTE], depois: ['status' => AccountsPayable::CANCELADO]);

        return back()->with('success', 'Conta cancelada.');
    }

    public function baixar(AccountsPayablePaymentRequest $request, AccountsPayable $conta): RedirectResponse
    {
        try {
            $baixa = $this->service->registrarBaixa($conta, $request->validated() + [
                'user_id' => $request->user()->id,
            ]);
        } catch (OperacaoFinanceiraInvalida $excecao) {
            return back()->withErrors(['valor' => $excecao->getMessage()])->withInput();
        }

        AuditLogger::log('baixar_conta_pagar', 'accounts_payable_pagamentos', $baixa->id, depois: $baixa->only(['accounts_payable_id', 'valor', 'data']));

        $conta->refresh();

        return back()->with('success', 'Baixa registrada. Saldo restante: R$ '.number_format($conta->saldo, 2, ',', '.').'.');
    }

    /**
     * Soma o saldo devedor real das contas do filtro, descontando as baixas.
     *
     * A subconsulta é correlacionada para não precisar carregar todas as
     * linhas na memória nem depender de `withSum` na agregação.
     */
    private function somarSaldo(Builder $query): float
    {
        return round((float) $query->sum(DB::raw(AccountsPayable::expressaoSaldo())), 2);
    }
}
