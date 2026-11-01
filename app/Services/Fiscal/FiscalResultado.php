<?php

namespace App\Services\Fiscal;

/**
 * Resposta normalizada de emitir, cancelar ou consultar.
 *
 * Cada provedor devolve o mesmo formato, então o serviço grava no banco sem
 * precisar saber qual API respondeu.
 */
final class FiscalResultado
{
    /**
     * @param  array<string, mixed>  $resposta  corpo bruto devolvido pela API
     */
    public function __construct(
        public bool $sucesso,
        public string $status,
        public ?string $mensagem = null,
        public ?string $chave = null,
        public ?string $numero = null,
        public ?string $serie = null,
        public ?string $protocolo = null,
        public ?string $xml = null,
        public ?string $pdfUrl = null,
        public array $resposta = [],
    ) {}
}
