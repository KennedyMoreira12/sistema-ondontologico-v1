<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O pacote existe, mas não está consumível agora (cancelado, concluído ou
 * vencido). Mesma ideia do `SaldoDeSessoesInsuficiente`: erro de regra de
 * negócio, não de servidor.
 */
class PacoteSessaoIndisponivel extends RuntimeException
{
    public static function paraPacote(string $nome, string $status): self
    {
        return new self("O pacote [{$nome}] não está disponível para consumo (status: {$status}).");
    }
}
