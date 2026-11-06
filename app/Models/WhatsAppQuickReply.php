<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resposta rápida que a recepção usa no atendimento manual.
 *
 * É conteúdo fixo por clínica (endereço, orientação de preparo, política de
 * cancelamento) que hoje vive num bloco de notas ao lado do telefone. Guardar
 * aqui evita que cada atendente escreva uma versão diferente da mesma
 * informação.
 *
 * Não envia nada sozinha: quem escolhe a resposta é o atendente.
 */
#[Fillable(['atalho', 'titulo', 'conteudo', 'is_active'])]
class WhatsAppQuickReply extends TenantModel
{
    protected $table = 'whatsapp_quick_replies';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
