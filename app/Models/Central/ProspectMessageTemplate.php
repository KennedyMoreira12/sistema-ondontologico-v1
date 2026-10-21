<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Modelo de mensagem comercial editável pelo superadministrador.
 */
#[Fillable(['key', 'name', 'body', 'is_active', 'sort_order'])]
class ProspectMessageTemplate extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    #[Scope]
    protected function activeOrdered(Builder $query): void
    {
        $query->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Troca os placeholders conhecidos pelos dados do contato. O texto
     * resultante é só uma SUGESTÃO para revisão antes do envio.
     *
     * @param  array<string, string>  $extra
     */
    public function render(Prospect $prospect, array $extra = []): string
    {
        $map = array_merge([
            'nome' => $prospect->name,
            'categoria' => $prospect->category ?: $prospect->type ?: 'seu negócio',
            'cidade' => $prospect->city,
            'remetente' => 'LJ Automação',
        ], $extra);

        $replace = [];
        foreach ($map as $key => $value) {
            $replace['{'.$key.'}'] = (string) $value;
        }

        return strtr($this->body, $replace);
    }
}
