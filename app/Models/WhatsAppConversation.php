<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['remote_jid', 'etapa', 'dados', 'expires_at'])]
class WhatsAppConversation extends TenantModel
{
    protected $table = 'whatsapp_conversations';

    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
