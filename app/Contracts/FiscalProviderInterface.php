<?php

namespace App\Contracts;

use App\Models\NotaFiscal;
use App\Services\Fiscal\FiscalResultado;

/**
 * Contrato que todo provedor de emissão fiscal precisa cumprir.
 *
 * O provedor não conhece regra de negócio nem banco: recebe a nota e o
 * payload normalizado, devolve um resultado. Quem persiste é o
 * NotaFiscalService.
 */
interface FiscalProviderInterface
{
    /**
     * Identificador curto do provedor (mock, sendnf, focusnfe...).
     */
    public function nome(): string;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function emitir(NotaFiscal $nota, array $payload): FiscalResultado;

    public function cancelar(NotaFiscal $nota, string $motivo): FiscalResultado;

    public function consultar(NotaFiscal $nota): FiscalResultado;
}
