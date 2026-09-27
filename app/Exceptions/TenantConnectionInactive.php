<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A conexão da clínica existe mas está desligada em `tenant_connections`.
 *
 * Existe como exceção própria, e não como `RuntimeException` genérica, para que
 * a camada HTTP consiga responder 403 (clínica desativada, não é culpa de quem
 * chamou) em vez de 500 (banco fora do ar, é falha nossa). Tratar os dois com o
 * mesmo status esconderia uma configuração errada atrás de erro de servidor.
 */
class TenantConnectionInactive extends RuntimeException
{
    public static function forClinic(string $clinicName): self
    {
        return new self("A conexão da clínica [{$clinicName}] está desativada.");
    }
}
