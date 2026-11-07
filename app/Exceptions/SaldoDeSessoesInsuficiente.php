<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O pacote não tem sessão disponível para consumo.
 *
 * Existe como exceção própria, e não como erro genérico, para que a camada
 * HTTP responda 422 (regra de negócio: o saldo acabou) em vez de 500 (falha
 * do servidor). Um saldo negativo aqui significaria que o paciente recebeu
 * mais sessão do que comprou — e a exceção existe justamente para nunca
 * deixar isso acontecer.
 */
class SaldoDeSessoesInsuficiente extends RuntimeException
{
    public static function paraPacote(string $nome, int $saldo): self
    {
        return new self("O pacote [{$nome}] está sem saldo de sessões (saldo: {$saldo}).");
    }
}
