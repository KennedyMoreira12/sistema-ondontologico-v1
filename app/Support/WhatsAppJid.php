<?php

namespace App\Support;

/**
 * O que é — e o que não é — um telefone no JID do WhatsApp.
 *
 * O JID de grupo termina em `@g.us` e carrega o id do grupo, não um número. O
 * código antigo extraía dígitos de qualquer JID, então uma mensagem de grupo
 * virava um "telefone" de 18–19 dígitos: o fluxo abria conversa, a IA respondia
 * e a resposta ia para um destino que a Evolution recusa — quando não caía no
 * número errado. A classificação fica aqui para webhook, fluxo e envio
 * concordarem sobre a mesma regra.
 */
final class WhatsAppJid
{
    public const SUFIXO_GRUPO = '@g.us';

    /**
     * Um JID de conversa coletiva.
     */
    public static function ehGrupo(?string $jid): bool
    {
        return is_string($jid) && str_ends_with(strtolower(trim($jid)), self::SUFIXO_GRUPO);
    }

    /**
     * Um destino individual válido: 10 a 15 dígitos, como o E.164 permite.
     *
     * Aceita o JID completo (`5511999999999@s.whatsapp.net`) e o número puro,
     * porque o n8n manda os dois formatos. Grupo nunca passa, mesmo que o id
     * tenha só dígitos.
     */
    public static function ehTelefone(?string $valor): bool
    {
        if (! is_string($valor) || self::ehGrupo($valor)) {
            return false;
        }

        $digitos = preg_replace('/\D/', '', $valor);

        return is_string($digitos) && strlen($digitos) >= 10 && strlen($digitos) <= 15;
    }
}
