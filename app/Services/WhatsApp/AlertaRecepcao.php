<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAlert;
use App\Models\WhatsAppConversation;
use App\Services\AuditLogger;

/**
 * Abre e resolve os alertas que colocam uma conversa na fila da recepção.
 *
 * Um alerta por conversa e tipo, enquanto estiver aberto: pedir humano três
 * vezes na mesma conversa é um pedido, não três. Reabrir só depois de o
 * anterior ser atendido, senão a fila vira ruído e ninguém olha mais.
 *
 * O alerta é dado da clínica (vive no banco dela) porque é operação de
 * atendimento, e a recepção de uma clínica não tem nada a ver com a fila da
 * outra.
 */
class AlertaRecepcao
{
    public function abrir(
        WhatsAppConversation|string $conversa,
        string $tipo,
        ?string $motivo = null,
        ?int $userId = null,
    ): WhatsAppAlert {
        $jid = $conversa instanceof WhatsAppConversation ? (string) $conversa->remote_jid : $conversa;
        $conversaId = $conversa instanceof WhatsAppConversation ? $conversa->id : null;

        $existente = WhatsAppAlert::query()
            ->where('remote_jid', $jid)
            ->where('tipo', $tipo)
            ->abertos()
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $alerta = WhatsAppAlert::create([
            'remote_jid' => $jid,
            'tipo' => $tipo,
            'motivo' => $motivo === null ? null : mb_substr($motivo, 0, 191),
        ]);

        AuditLogger::log('alerta_recepcao', 'whatsapp_alerts', $alerta->id, depois: [
            'tipo' => $tipo,
            'conversa_id' => $conversaId,
        ]);

        return $alerta;
    }

    /**
     * Marca como atendidos os alertas abertos da conversa.
     *
     * Chamado quando um humano assume: a partir daí a conversa está com gente,
     * e manter o alerta aberto faria a recepção olhar de novo o que já está na
     * mão dela.
     */
    public function resolver(WhatsAppConversation|string $conversa, ?int $userId = null): int
    {
        $jid = $conversa instanceof WhatsAppConversation ? (string) $conversa->remote_jid : $conversa;

        $abertos = WhatsAppAlert::query()
            ->where('remote_jid', $jid)
            ->abertos()
            ->get();

        foreach ($abertos as $alerta) {
            $alerta->marcarAtendido($userId);
        }

        return $abertos->count();
    }
}
