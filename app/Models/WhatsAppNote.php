<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Anotação interna sobre uma conversa.
 *
 * Nunca é enviada ao paciente e nunca é lida pelo bot. Fica em tabela separada
 * de `whatsapp_messages` justamente para que uma exportação de histórico, um
 * relatório ou uma futura tela não misturem nota de equipe com fala do
 * paciente.
 *
 * `user_id` aponta para o usuário do banco central; por isso não há chave
 * estrangeira — a tabela está no banco da clínica e o usuário não.
 */
#[Fillable(['remote_jid', 'user_id', 'conteudo'])]
class WhatsAppNote extends TenantModel
{
    protected $table = 'whatsapp_notes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }
}
