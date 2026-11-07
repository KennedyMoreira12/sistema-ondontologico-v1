<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pacote de sessões contratado pelo paciente.
 *
 * `sessoes_usadas` é o saldo: ele NUNCA passa de `total_sessoes` e nunca
 * fica negativo. Quem garante isso é o consumo transacional (ver
 * `App\Services\Fisioterapia\PacoteSessaoService`), e não a tela — a tela
 * pode estar aberta em dois computadores ao mesmo tempo.
 */
#[Fillable([
    'paciente_id', 'profissional_id', 'nome', 'servico',
    'total_sessoes', 'sessoes_usadas', 'valor_sessao', 'valor_total',
    'status', 'validade_em',
])]
class PacoteSessao extends TenantModel
{
    /**
     * O pluralizador do Laravel transformaria `PacoteSessao` em
     * `pacote_sessaos`, que não é o nome da tabela. O nome é fixado aqui para
     * que o model e a migration não{divergir.
     */
    protected $table = 'pacotes_sessao';

    public const ATIVO = 'ativo';

    public const CONCLUIDO = 'concluido';

    public const CANCELADO = 'cancelado';

    /**
     * `validade_em` é data; `total_sessoes` e `sessoes_usadas` são inteiros,
     * porque o saldo é calculado por conta própria e não deve virar string.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_sessoes' => 'integer',
            'sessoes_usadas' => 'integer',
            'validade_em' => 'date',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function consumos(): HasMany
    {
        // A coluna é `pacote_id`, não `pacote_sessao_id`: sem a chave explícita
        // o Eloquent deduz o nome a partir do model pai e a tela do pacote
        // estoura com "no such column".
        return $this->hasMany(ConsumoSessao::class, 'pacote_id');
    }

    /**
     * Sessões que ainda podem ser consumidas.
     */
    public function saldo(): int
    {
        return max(0, $this->total_sessoes - $this->sessoes_usadas);
    }

    /**
     * Nome usado pelo restante da aplicação: o saldo é "restantes".
     */
    public function restantes(): int
    {
        return $this->saldo();
    }

    public function consumidas(): int
    {
        return min($this->total_sessoes, $this->sessoes_usadas);
    }

    public function percentualUsado(): float
    {
        if ($this->total_sessoes <= 0) {
            return 0.0;
        }

        return round(min(100, ($this->sessoes_usadas / $this->total_sessoes) * 100), 1);
    }

    public function estaAtivo(): bool
    {
        return $this->status === self::ATIVO
            && $this->saldo() > 0
            && ($this->validade_em === null || $this->validade_em->isFuture() || $this->validade_em->isToday());
    }

    /**
     * Pacotes que ainda podem ser usados pela clínica.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', self::ATIVO)->whereColumn('sessoes_usadas', '<', 'total_sessoes');
    }
}
