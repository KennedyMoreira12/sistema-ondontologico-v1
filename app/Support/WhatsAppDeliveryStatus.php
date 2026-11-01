<?php

namespace App\Support;

/**
 * Tradução do estado de entrega da Evolution para o vocabulário do histórico.
 *
 * A Evolution usa nomes em maiúsculas (`SERVER_ACK`, `DELIVERY_ACK`, `READ`) e
 * manda esses eventos FORA DE ORDEM: um `READ` pode chegar antes de um
 * `DELIVERY_ACK` reentregue. Por isso a atualização é monotônica — um evento
 * antigo nunca rebaixa o que já foi confirmado — em vez de "o último que
 * chegar vence".
 *
 * `failed` é terminal: uma mensagem marcada como falha pelo provedor não volta
 * a "entregue" por um evento atrasado. Se a Evolution realmente entregar, o
 * operador vê a mensagem na conversa e o histórico fica conservador, que é
 * melhor do que afirmar entrega que ninguém confirmou.
 */
final class WhatsAppDeliveryStatus
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const READ = 'read';

    public const FAILED = 'failed';

    /**
     * Peso de cada estado para a atualização monotônica.
     *
     * @var array<string, int>
     */
    protected const RANK = [
        self::PENDING => 0,
        self::SENT => 1,
        self::DELIVERED => 2,
        self::READ => 3,
        self::FAILED => 4,
    ];

    public static function fromEvolution(?string $status): ?string
    {
        if ($status === null || trim($status) === '') {
            return null;
        }

        return match (strtoupper(trim($status))) {
            'PENDING', 'QUEUED' => self::PENDING,
            'SERVER_ACK', 'SENT' => self::SENT,
            'DELIVERY_ACK', 'DELIVERED' => self::DELIVERED,
            'READ', 'PLAYED' => self::READ,
            'ERROR', 'FAILED', 'FAILURE' => self::FAILED,
            default => null,
        };
    }

    /**
     * O novo estado pode substituir o atual?
     *
     * Sem estado atual, qualquer estado conhecido entra. Com estado atual, só
     * entra o que tem peso maior ou igual — reentrega do mesmo evento é
     * inofensiva e um evento atrasado não desfaz uma confirmação.
     */
    public static function shouldApply(?string $atual, string $novo): bool
    {
        if ($atual === null) {
            return true;
        }

        if ($atual === self::FAILED) {
            return false;
        }

        return (self::RANK[$novo] ?? 0) >= (self::RANK[$atual] ?? 0);
    }
}
