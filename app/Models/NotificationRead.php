<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca de leitura de uma notificação por um usuário.
 *
 * A linha só existe depois que a pessoa leu: não há "não lida" gravada, e sim
 * a ausência da linha. Isso evita criar um registro por usuário para cada
 * alerta gerado.
 */
#[Fillable(['notification_id', 'user_id', 'read_at'])]
class NotificationRead extends TenantModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
