<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppNote;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Notas internas de uma conversa.
 *
 * Nota é o que a recepção precisa lembrar e o paciente não pode ler: "prefere
 * contato à tarde", "veio por indicação da Dra. Ana". Por isso vive em
 * `whatsapp_notes`, e não em `whatsapp_messages`: misturar as duas faria uma
 * exportação de histórico ou um relatório entregar nota de equipe como se
 * fosse fala do paciente.
 *
 * Nada aqui envia mensagem. O único destino de uma nota é a tela de quem
 * atende.
 */
class NotasInternas
{
    public const LIMITE_CARACTERES = 4000;

    public function criar(string $remoteJid, ?int $userId, string $conteudo): WhatsAppNote
    {
        $conteudo = trim($conteudo);

        if ($conteudo === '') {
            throw new \InvalidArgumentException('A nota interna não pode ficar vazia.');
        }

        $nota = WhatsAppNote::create([
            'remote_jid' => $remoteJid,
            'user_id' => $userId,
            'conteudo' => mb_substr($conteudo, 0, self::LIMITE_CARACTERES),
        ]);

        /**
         * Auditoria registra QUEM escreveu e SOBRE qual conversa — nunca o
         * texto. `AuditLogger` já remove a chave `conteudo`; a chamada repete a
         * intenção para que uma mudança futura no sanitizador não faça a nota
         * vazar por log.
         */
        AuditLogger::log('nota_interna', 'whatsapp_notes', $nota->id, depois: [
            'remote_jid' => $remoteJid,
            'user_id' => $userId,
        ]);

        return $nota;
    }

    /**
     * @return Collection<int, WhatsAppNote>
     */
    public function listar(string $remoteJid, int $limite = 50): Collection
    {
        return WhatsAppNote::query()
            ->where('remote_jid', $remoteJid)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limite, 200)))
            ->get();
    }
}
