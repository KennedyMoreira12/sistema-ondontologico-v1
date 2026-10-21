<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma sessão consumida de um pacote.
 *
 * É o extrato: existe uma linha por sessão usada, e ela é criada na mesma
 * transação que debita o saldo do pacote.
 */
#[Fillable([
    'pacote_id', 'paciente_id', 'profissional_id', 'evolucao_sessao_id',
    'data', 'valor', 'observacoes',
])]
class ConsumoSessao extends TenantModel
{
    /**
     * Nome fixado: o pluralizador geraria `consumo_sessaos`.
     */
    protected $table = 'consumo_sessao';

    public function pacote(): BelongsTo
    {
        return $this->belongsTo(PacoteSessao::class);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function evolucao(): BelongsTo
    {
        return $this->belongsTo(EvolucaoSessao::class);
    }
}
