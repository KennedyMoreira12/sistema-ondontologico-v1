<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Operação recusada por regra de negócio financeira (conta cancelada, valor
 * acima do saldo, baixa repetida...). Vira 422 na tela, não 500.
 */
class OperacaoFinanceiraInvalida extends RuntimeException
{
    public static function contaCancelada(string $nome): self
    {
        return new self("A conta [{$nome}] está cancelada e não aceita movimentação.");
    }

    public static function valorAcimaDoSaldo(float $valor, float $saldo): self
    {
        return new self(
            'O valor informado ('.number_format($valor, 2, ',', '.').') é maior que o saldo devedor ('
            .number_format($saldo, 2, ',', '.').').'
        );
    }

    public static function valorInvalido(): self
    {
        return new self('Informe um valor maior que zero.');
    }

    public static function baixaDuplicada(): self
    {
        return new self('Esta baixa já foi registrada. Recarregue a tela antes de tentar novamente.');
    }

    public static function contaInexistente(): self
    {
        return new self('A conta informada não existe mais.');
    }

    public static function contaComBaixas(): self
    {
        return new self('Uma conta com baixas registradas não pode ser cancelada.');
    }
}
