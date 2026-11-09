<?php

namespace App\Services\Financeiro;

use App\Exceptions\OperacaoFinanceiraInvalida;
use App\Models\AccountsReceivable;
use App\Models\Payment;

/**
 * Baixa de conta a receber.
 *
 * A baixa inteira acontece em uma transação com bloqueio da conta: duas
 * recepções simultâneas não podem ler o mesmo saldo e gravar as duas. Antes
 * esta regra vivia no controller, sem transação, e um duplo clique podia
 * deixar `valor_pago` acima do valor da conta.
 */
class RecebimentoService
{
    /**
     * @param  array{valor: float|int|string, payment_method_id: int, data?: mixed, observacoes?: string|null, servico?: string|null, user_id?: int|null}  $dados
     */
    public function receber(AccountsReceivable $conta, array $dados): Payment
    {
        $conexao = $conta->getConnection();

        return $conexao->transaction(function () use ($conta, $dados): Payment {
            $travada = AccountsReceivable::query()
                ->whereKey($conta->getKey())
                ->lockForUpdate()
                ->first();

            if ($travada === null) {
                throw OperacaoFinanceiraInvalida::contaInexistente();
            }

            if ($travada->status === 'pago') {
                throw new OperacaoFinanceiraInvalida('Esta conta já está totalmente recebida.');
            }

            $valor = round((float) $dados['valor'], 2);

            if ($valor <= 0) {
                throw OperacaoFinanceiraInvalida::valorInvalido();
            }

            $desconto = round((float) ($dados['desconto'] ?? 0), 2);

            if ($desconto < 0) {
                throw OperacaoFinanceiraInvalida::valorInvalido();
            }

            $saldoOriginal = round((float) $travada->valor - (float) $travada->valor_pago, 2);
            $saldoAposDesconto = round($saldoOriginal - $desconto, 2);

            if ($valor > $saldoAposDesconto) {
                throw OperacaoFinanceiraInvalida::valorAcimaDoSaldo($valor, $saldoAposDesconto);
            }

            $pagamento = Payment::create([
                'accounts_receivable_id' => $travada->id,
                'paciente_id' => $travada->paciente_id,
                'payment_method_id' => $dados['payment_method_id'],
                'user_id' => $dados['user_id'] ?? auth()->id(),
                'servico' => $dados['servico'] ?? $travada->servico,
                'insurance_plan_id' => $dados['insurance_plan_id'] ?? null,
                'origem' => 'manual',
                'data' => $dados['data'] ?? today(),
                'valor' => $valor,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            $novoPago = round((float) $travada->valor_pago + $valor, 2);
            $novoDesconto = round((float) $travada->desconto + $desconto, 2);

            $travada->forceFill([
                'valor_pago' => $novoPago,
                'desconto' => $novoDesconto,
                'status' => ($novoPago + $novoDesconto) >= round((float) $travada->valor, 2) ? 'pago' : 'parcial',
            ])->save();

            return $pagamento;
        });
    }

    /**
     * Saldo devedor calculado no momento, sem confiar no status gravado.
     */
    public function saldo(AccountsReceivable $conta): float
    {
        return round(max(0, (float) $conta->valor - (float) $conta->valor_pago), 2);
    }
}
