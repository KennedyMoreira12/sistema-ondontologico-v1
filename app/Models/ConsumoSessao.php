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
    'appointment_id', 'reposicao', 'data', 'valor', 'observacoes',
])]
class ConsumoSessao extends TenantModel
{
    /**
     * Nome fixado: o pluralizador geraria `consumo_sessaos`.
     */
    protected $table = 'consumo_sessao';

    /**
     * `data` é data: sem o cast a coluna da lista de consumos chama
     * `->format()` em string e a tela estoura 500.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'reposicao' => 'boolean',
        ];
    }

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
        // A coluna é `evolucao_sessao_id`; sem a chave explícita o Eloquent
        // procura `evolucao_id` e a tela do pacote estoura.
        return $this->belongsTo(EvolucaoSessao::class, 'evolucao_sessao_id');
    }

    /**
     * Atendimento que originou o débito da sessão.
     *
     * É o que impede o mesmo atendimento de debitar o pacote duas vezes: o
     * serviço procura um consumo existente antes de criar outro.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
