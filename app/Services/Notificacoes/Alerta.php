<?php

namespace App\Services\Notificacoes;

use DateTimeInterface;

/**
 * Um alerta pronto para virar linha em `notifications`.
 *
 * O coletor descreve o fato; o `Notificador` decide como gravar. Manter os
 * dois separados é o que permite testar a regra de cada módulo sem tocar no
 * banco.
 */
final class Alerta
{
    public function __construct(
        public readonly string $categoria,
        public readonly string $tipo,
        public readonly string $prioridade,
        public readonly string $titulo,
        public readonly ?string $mensagem = null,
        public readonly ?string $url = null,
        public readonly ?string $permissao = null,
        public readonly ?string $referenciaType = null,
        public readonly ?int $referenciaId = null,
        public readonly ?string $dedupeKey = null,
        public readonly ?DateTimeInterface $expiraEm = null,
    ) {}

    /**
     * Chave de deduplicação.
     *
     * Quando o coletor não informa uma, a identidade do registro de origem
     * basta: o mesmo tipo para o mesmo registro é o mesmo alerta.
     */
    public function chave(): string
    {
        if ($this->dedupeKey !== null && $this->dedupeKey !== '') {
            return $this->dedupeKey;
        }

        return $this->tipo.':'.($this->referenciaType ?? 'geral').':'.($this->referenciaId ?? '0');
    }
}
