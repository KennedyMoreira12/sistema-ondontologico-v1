<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Uma resposta automática por texto, cadastrada pela clínica.
 *
 * Existe para o que a IA não precisa responder: endereço, horário, convênio,
 * formas de pagamento. Texto fixo, revisado por quem é dono da informação, é
 * mais barato e mais previsível do que gerar a mesma frase por modelo — e não
 * depende de provedor no ar.
 *
 * `tipo`:
 * - `exact`: o texto do paciente é igual ao gatilho (sem diferenciar caixa);
 * - `contains`: contém o gatilho em qualquer posição;
 * - `regex`: o gatilho é uma expressão regular completa, com delimitadores.
 *
 * `escopo`:
 * - `menu`: só responde na etapa de menu;
 * - `livre`: responde em etapas sem contrato próprio, nunca no meio de um
 *   cadastro ou da escolha de horário.
 *
 * `prioridade` menor responde primeiro; empate é desempatado pelo id para a
 * resposta não depender da ordem do banco.
 */
#[Fillable([
    'nome', 'tipo', 'gatilho', 'resposta', 'prioridade', 'escopo', 'is_active',
])]
class WhatsAppRule extends TenantModel
{
    protected $table = 'whatsapp_rules';

    public const EXACT = 'exact';

    public const CONTAINS = 'contains';

    public const REGEX = 'regex';

    public const ESCOPO_MENU = 'menu';

    public const ESCOPO_LIVRE = 'livre';

    /**
     * @var list<string>
     */
    public const TIPOS = [self::EXACT, self::CONTAINS, self::REGEX];

    /**
     * @var list<string>
     */
    public const ESCOPOS = [self::ESCOPO_MENU, self::ESCOPO_LIVRE];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prioridade' => 'integer',
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

    /**
     * Ordem de avaliação: prioridade e, no empate, a regra mais antiga.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeNaOrdem(Builder $query): Builder
    {
        return $query->orderBy('prioridade')->orderBy('id');
    }
}
