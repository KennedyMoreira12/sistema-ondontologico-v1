<?php

namespace App\Services\Financeiro;

use App\Exceptions\OperacaoFinanceiraInvalida;
use App\Models\AccountsPayable;
use App\Models\AccountsPayablePayment;

/**
 * Criação e baixa de contas a pagar.
 *
 * A baixa é transacional e trava a linha da conta. O valor pago é sempre a
 * soma do extrato (`accounts_payable_pagamentos`), nunca uma coluna paralela
 * que possa divergir. Quando a soma alcança o valor da conta, ela vira
 * `pago`; antes disso continua `pendente` (o "atrasado" é calculado pela
 * data, para não envelhecer gravado).
 */
class ContasPagarService
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function criar(array $dados): AccountsPayable
    {
        $valor = round((float) ($dados['valor'] ?? 0), 2);

        if ($valor <= 0) {
            throw OperacaoFinanceiraInvalida::valorInvalido();
        }

        return AccountsPayable::create([
            'supplier_id' => $dados['supplier_id'] ?? null,
            'financeiro_categoria_id' => $dados['financeiro_categoria_id'] ?? null,
            'nome_conta' => $dados['nome_conta'],
            'descricao' => $dados['descricao'] ?? null,
            'valor' => $valor,
            'data_vencimento' => $dados['data_vencimento'],
            'data_pagamento' => null,
            'status' => AccountsPayable::PENDENTE,
            'tipo' => $dados['tipo'] ?? 'outros',
            'tipo_documento' => $dados['tipo_documento'] ?? 'outros',
            'observacoes' => $dados['observacoes'] ?? null,
        ]);
    }

    /**
     * Registra uma baixa (total ou parcial).
     *
     * `idempotency_key` evita que um duplo envio do formulário crie duas
     * baixas iguais: a segunda chamada devolve a baixa já criada.
     *
     * @param  array{valor: float|int|string, payment_method_id?: int|null, data?: mixed, observacoes?: string|null, user_id?: int|null, idempotency_key?: string|null}  $dados
     */
    public function registrarBaixa(AccountsPayable $conta, array $dados): AccountsPayablePayment
    {
        $conexao = $conta->getConnection();

        return $conexao->transaction(function () use ($conta, $dados): AccountsPayablePayment {
            $chave = $dados['idempotency_key'] ?? null;

            $travada = AccountsPayable::query()
                ->whereKey($conta->getKey())
                ->lockForUpdate()
                ->first();

            if ($travada === null) {
                throw OperacaoFinanceiraInvalida::contaInexistente();
            }

            /*
             * A chave é conferida DEPOIS do bloqueio da conta: dois envios do
             * mesmo formulário entram em fila e o segundo enxerga a baixa
             * criada pelo primeiro. Se a mesma chave aparecer em OUTRA conta,
             * a operação é recusada em vez de devolver a baixa errada — a
             * chave é única no tenant e nunca deve atravessar contas.
             */
            if (is_string($chave) && $chave !== '') {
                $existente = AccountsPayablePayment::query()
                    ->where('idempotency_key', $chave)
                    ->first();

                if ($existente !== null) {
                    if ((int) $existente->accounts_payable_id !== (int) $travada->getKey()) {
                        throw OperacaoFinanceiraInvalida::baixaDuplicada();
                    }

                    return $existente;
                }
            }

            if ($travada->status === AccountsPayable::CANCELADO) {
                throw OperacaoFinanceiraInvalida::contaCancelada($travada->nome_conta);
            }

            $valor = round((float) $dados['valor'], 2);

            if ($valor <= 0) {
                throw OperacaoFinanceiraInvalida::valorInvalido();
            }

            $pago = round((float) $travada->pagamentos()->sum('valor'), 2);
            $saldo = round((float) $travada->valor - $pago, 2);

            if ($valor > $saldo) {
                throw OperacaoFinanceiraInvalida::valorAcimaDoSaldo($valor, $saldo);
            }

            $pagamento = AccountsPayablePayment::create([
                'accounts_payable_id' => $travada->id,
                'payment_method_id' => $dados['payment_method_id'] ?? null,
                'user_id' => $dados['user_id'] ?? auth()->id(),
                'idempotency_key' => is_string($chave) && $chave !== '' ? $chave : null,
                'data' => $dados['data'] ?? today(),
                'valor' => $valor,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            $totalPago = round($pago + $valor, 2);

            $travada->forceFill([
                'status' => $totalPago >= round((float) $travada->valor, 2)
                    ? AccountsPayable::PAGO
                    : AccountsPayable::PENDENTE,
                'data_pagamento' => $totalPago >= round((float) $travada->valor, 2)
                    ? ($dados['data'] ?? today())
                    : null,
            ])->save();

            return $pagamento;
        });
    }

    /**
     * Cancela a conta de forma transacional.
     *
     * A checagem de baixas fica DENTRO da transação e com a linha travada:
     * sem isso, uma baixa registrada entre a checagem do controller e o
     * cancelamento deixaria uma conta cancelada com pagamento no extrato.
     */
    public function cancelar(AccountsPayable $conta): AccountsPayable
    {
        $conexao = $conta->getConnection();

        return $conexao->transaction(function () use ($conta): AccountsPayable {
            $travada = AccountsPayable::query()
                ->whereKey($conta->getKey())
                ->lockForUpdate()
                ->first();

            if ($travada === null) {
                throw OperacaoFinanceiraInvalida::contaInexistente();
            }

            if ($travada->status === AccountsPayable::CANCELADO) {
                return $travada;
            }

            if ($travada->pagamentos()->exists()) {
                throw OperacaoFinanceiraInvalida::contaComBaixas();
            }

            $travada->forceFill([
                'status' => AccountsPayable::CANCELADO,
                'data_pagamento' => null,
            ])->save();

            return $travada->refresh();
        });
    }
}
