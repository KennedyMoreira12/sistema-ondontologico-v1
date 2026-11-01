<?php

namespace App\Services\Fiscal;

use RuntimeException;

/**
 * Falha de comunicação ou recusa do provedor fiscal.
 *
 * O NotaFiscalService captura esta exceção e grava a nota como rejeitada,
 * com a mensagem em `motivo_rejeicao`, em vez de estourar 500 na tela.
 */
class FiscalException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $resposta
     */
    public function __construct(
        string $message,
        public readonly ?string $motivo = null,
        public readonly array $resposta = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
