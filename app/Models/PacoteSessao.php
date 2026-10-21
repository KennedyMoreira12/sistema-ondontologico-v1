<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    'paciente_id', 'profissional_id', 'nome',
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
        return $this->hasMany(ConsumoSessao::class);
    }

    /**
     * Sessões que ainda podem ser consumidas.
     */
    public function saldo(): int
    {
        return max(0, $this->total_sessoes - $this->sessoes_usadas);
    }

    public function estaAtivo(): bool
    {
        return $this->status === self::ATIVO && $this->saldo() > 0;
    }
}
