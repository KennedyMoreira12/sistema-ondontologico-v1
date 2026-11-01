<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Evolution API não pode ser chamada para esta clínica.
 *
 * Existe como exceção própria, e não como `RuntimeException` genérica, para que a
 * camada HTTP responda 503 — "a integração não está pronta" — em vez de 500. São
 * problemas diferentes na prateleira de quem opera: 500 manda o n8n reentregar
 * em loop e sugere defeito no sistema; 503 diz que a clínica ainda não tem
 * instância ou token, e isso se resolve no painel.
 */
class EvolutionIndisponivel extends RuntimeException
{
    public static function semIntegracao(string $clinicName): self
    {
        return new self("A clínica [{$clinicName}] não tem instância ou token da Evolution API configurados.");
    }

    public static function semUrl(): self
    {
        return new self('EVOLUTION_API_URL não está configurada.');
    }

    public static function semChaveAdministrativa(): self
    {
        return new self('A chave administrativa da Evolution API (EVOLUTION_API_KEY) não está configurada.');
    }
}
