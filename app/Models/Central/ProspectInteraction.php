<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma tentativa de contato / movimentação de uma oportunidade.
 *
 * É também o histórico comercial: data, canal, modelo usado (template_id) e
 * resultado registrado. Não guarda o texto enviado nem conversa pessoal.
 */
#[Fillable([
    'prospect_id', 'template_id', 'admin_user_id', 'channel',
    'status_before', 'status_after', 'result', 'notes', 'interacted_at',
])]
class ProspectInteraction extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interacted_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProspectMessageTemplate::class, 'template_id');
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function channelLabel(): string
    {
        return (string) config("prospeccao.channels.{$this->channel}", $this->channel);
    }
}
