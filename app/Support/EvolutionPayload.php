<?php

namespace App\Support;

/**
 * Leitura do payload que a Evolution API manda no webhook.
 *
 * Tudo aqui é tolerante a formato por um motivo concreto: a Evolution nomeia o
 * evento de duas maneiras dependendo da versão (`messages.upsert` na 2.x,
 * `MESSAGES_UPSERT` em instância criada por versão antiga) e o campo `message`
 * do WhatsApp muda de forma conforme o tipo — texto puro em `conversation`, texto
 * em `extendedTextMessage.text`, legenda em `imageMessage.caption`, e assim por
 * diante. Um `data['message']['conversation']` fixo faria toda foto e todo áudio
 * chegar sem texto, e o n8n responderia "não entendi" a uma mensagem que o
 * paciente achou clara.
 *
 * Nenhum método estoura por payload incompleto: um webhook que a Evolution
 * mandou e que este app não entende tem de ser ignorado em silêncio, não virar
 * 500 que faz ela reentregar em loop.
 */
final class EvolutionPayload
{
    /**
     * Eventos que viram linha em `whatsapp_messages`.
     *
     * `messages.upsert` é o de mensagem entrando ou saindo; `messages.update` é
     * a mudança de estado de uma que já existe (virou enviada, foi lida) e não
     * entra, porque reentregar isso ao n8n faria o bot responder de novo a uma
     * mensagem que ele já atendeu.
     */
    public const EVENTO_MENSAGEM = 'MESSAGES_UPSERT';

    /**
     * Eventos que o app registra em log e devolve 200 sem persistir.
     *
     * `connection.update` e `qrcode.updated` são o estado do WhatsApp da clínica.
     * Nenhum workflow responde a eles, mas descartá-los sem registro tiraria do
     * operador a única pista de por que uma clínica parou de receber mensagem.
     */
    public const EVENTOS_SEM_MENSAGEM = [
        'CONNECTION_UPDATE',
        'QRCODE_UPDATED',
        'APPLICATION_STARTUP',
    ];

    /**
     * Normaliza o nome do evento para a forma com que o resto do app compara.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function evento(array $payload): ?string
    {
        $evento = $payload['event'] ?? null;

        if (! is_string($evento) || $evento === '') {
            return null;
        }

        // `messages.upsert` e `MESSAGES_UPSERT` viram a mesma coisa; o hífen
        // também entra porque `send-message` aparece na URL por evento.
        $normalizado = str_replace(['.', '-'], '_', strtoupper($evento));

        return $normalizado === '' ? null : $normalizado;
    }

    /**
     * O evento traz uma mensagem que precisa ser registrada?
     *
     * @param  array<string, mixed>  $payload
     */
    public static function temMensagem(array $payload): bool
    {
        return self::evento($payload) === self::EVENTO_MENSAGEM;
    }

    /**
     * Id da mensagem no WhatsApp, que é o que a Evolution reentrega a cada retry.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function chave(array $payload): ?string
    {
        $chave = self::data($payload)['key']['id'] ?? null;

        return is_string($chave) && $chave !== '' ? $chave : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function enviadaPelaClinica(array $payload): bool
    {
        return (bool) (self::data($payload)['key']['fromMe'] ?? false);
    }

    /**
     * JID do outro lado da conversa, preferindo o que dá para buscar paciente.
     *
     * O WhatsApp passou a mandar `@lid` em vez de `@s.whatsapp.net` para contatos
     * novos, e `@lid` não é telefone: é um id opaco que nunca casa com
     * `pacientes.celular`. A Evolution costuma trocar um pelo outro antes de
     * enviar o webhook, mas não em toda versão — então, se vier `@lid`, o
     * `remoteJidAlt` é o número real e tem precedência. Sem essa precedência,
     * todo paciente novo vira "não encontrado" na busca da API.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function remoteJid(array $payload): ?string
    {
        $jid = self::data($payload)['key']['remoteJid'] ?? null;
        $alternativo = self::data($payload)['key']['remoteJidAlt'] ?? null;

        if (is_string($alternativo) && $alternativo !== '') {
            if (! is_string($jid) || $jid === '' || str_contains($jid, '@lid')) {
                return $alternativo;
            }
        }

        return is_string($jid) && $jid !== '' ? $jid : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function pushName(array $payload): ?string
    {
        $nome = self::data($payload)['pushName'] ?? null;

        return is_string($nome) && $nome !== '' ? $nome : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function messageType(array $payload): ?string
    {
        $tipo = self::data($payload)['messageType'] ?? null;

        return is_string($tipo) && $tipo !== '' ? $tipo : null;
    }

    /**
     * Unix timestamp em segundos, como o WhatsApp manda.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function timestamp(array $payload): ?int
    {
        $timestamp = self::data($payload)['messageTimestamp'] ?? null;

        return is_numeric($timestamp) ? (int) $timestamp : null;
    }

    /**
     * O texto da mensagem, seja ela de que tipo for.
     *
     * Mídia vem sem texto e com legenda opcional; a legenda entra como corpo
     * porque é ela que o paciente escreveu. Áudio, vídeo e figurinha não têm
     * texto nenhum, e devolver `null` é o correto — inventar um "[áudio]" faria o
     * n8n responder a um conteúdo que ele não recebeu.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function texto(array $payload): ?string
    {
        $data = self::data($payload);
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];

        $candidatos = [
            $message['conversation'] ?? null,
            $message['extendedTextMessage']['text'] ?? null,
            $message['imageMessage']['caption'] ?? null,
            $message['videoMessage']['caption'] ?? null,
            $message['documentMessage']['caption'] ?? null,
            $message['documentWithCaptionMessage']['message']['documentMessage']['caption'] ?? null,
            $message['buttonsResponseMessage']['selectedButtonId'] ?? null,
            $message['listResponseMessage']['singleSelectReply']['selectedRowId'] ?? null,
            $message['reactionMessage']['emoji'] ?? null,
            $message['contactMessage']['displayName'] ?? null,
        ];

        foreach ($candidatos as $candidato) {
            if (is_string($candidato) && trim($candidato) !== '') {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected static function data(array $payload): array
    {
        return is_array($payload['data'] ?? null) ? $payload['data'] : [];
    }
}
