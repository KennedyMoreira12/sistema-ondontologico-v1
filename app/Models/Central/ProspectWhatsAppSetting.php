<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Configuração da instância Evolution EXCLUSIVA de prospecção.
 *
 * Tabela de linha única (o serviço sempre usa a primeira). É separada de
 * `clinics` de propósito: nada aqui pode reaproveitar a instância de uma clínica.
 */
#[Fillable([
    'instance_name', 'instance_uuid', 'instance_token', 'status',
    'last_state', 'last_state_at', 'connected_at', 'last_error',
])]
class ProspectWhatsAppSetting extends CentralModel
{
    /**
     * O pluralizador do Laravel geraria `prospect_whats_app_settings` (separa
     * "WhatsApp"); a migration e todo o restante usam `prospect_whatsapp_settings`.
     */
    protected $table = 'prospect_whatsapp_settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'instance_token' => 'encrypted',
            'last_state_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
