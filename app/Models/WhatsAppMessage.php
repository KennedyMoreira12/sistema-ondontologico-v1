<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Uma mensagem que passou pelo WhatsApp da clínica.
 *
 * Vive no banco da clínica porque o histórico de conversa é dado de atendimento:
 * quem tem acesso a ele é a equipe da clínica e o n8n dela, nunca o operador da
 * plataforma. Isso também faz do banco a fronteira natural de isolamento — uma
 * clínica não enxerga a mensagem de outra nem pela consulta mais esperta.
 *
 * O que a Evolution manda tem campo de sessão e chave de mídia que nada aqui
 * consome. O `payload` guarda só o que o atendimento precisa, e `body` é o texto
 * já achatado dos tipos que o WhatsApp envia em formatos diferentes.
 */
#[Fillable([
    'message_key', 'from_me', 'event', 'message_type',
    'remote_jid', 'push_name', 'body', 'payload',
    'sent_at', 'forwarded_at', 'forward_error',
    'ai_intent', 'ai_handled', 'ai_error',
    'delivery_status', 'delivered_at', 'read_at', 'correlation_id',
])]
class WhatsAppMessage extends TenantModel
{
    /**
     * Fixo de propósito: o pluralizador do Laravel separa o "App" de "WhatsApp" e
     * derivaria `whats_app_messages`, que não é a tabela da migration. Nome de
     * tabela por convenção é bom enquanto a convenção existe; aqui ela choca com o
     * nome do produto, e a divergência só apareceria como "no such table" em
     * produção.
     */
    protected $table = 'whatsapp_messages';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_me' => 'boolean',
            'payload' => 'array',
            'sent_at' => 'datetime',
            'forwarded_at' => 'datetime',
            'ai_handled' => 'boolean',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * O telefone do outro lado da conversa, só dígitos e com código do país.
     *
     * O `remote_jid` chega como `5511999999999@s.whatsapp.net`, e JID é
     * identificador de sessão, não telefone. A API interna busca paciente por
     * número, então é o que o n8n precisa receber: mandar o JID daria "paciente
     * não encontrado" em toda consulta, sem erro aparente.
     */
    public function telefone(): ?string
    {
        if ($this->remote_jid === null) {
            return null;
        }

        $telefone = preg_replace('/\D/', '', $this->remote_jid);

        return ($telefone === null || $telefone === '') ? null : $telefone;
    }

    /**
     * Mensagem recebida do paciente, em vez de enviada pela clínica.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('from_me', false);
    }

    /**
     * Mensagem que o n8n ainda não recebeu — nem com sucesso, nem com erro.
     *
     * É o filtro de quem vai reenviar depois de uma queda do n8n: uma mensagem
     * com `forward_error` preenchido já foi tentada e está esperando o operador
     * decidir, e quem a reenviasse de novo entraria em loop.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('forwarded_at')->whereNull('forward_error');
    }
}
