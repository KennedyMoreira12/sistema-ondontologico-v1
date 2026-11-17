<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma sessão de laser aplicada em um paciente.
 *
 * `numero_sessao` é calculado pelo `AplicacaoLaserService` dentro de uma
 * transação, por paciente e região: é o histórico que mostra a evolução do
 * tratamento, e não uma cobrança. O pacote é opcional e serve para comparar a
 * sessão com o total previsto no modelo.
 */
#[Fillable([
    'paciente_id', 'regiao_id', 'pacote_id', 'profissional_id',
    'data', 'numero_sessao', 'observacoes',
])]
class AplicacaoLaser extends TenantModel
{
    protected $table = 'laser_aplicacoes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'numero_sessao' => 'integer',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function regiao(): BelongsTo
    {
        return $this->belongsTo(RegiaoLaser::class, 'regiao_id');
    }

    public function pacote(): BelongsTo
    {
        return $this->belongsTo(PacoteLaser::class, 'pacote_id');
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /**
     * A sessão passou do total previsto no pacote?
     *
     * Não é erro: a clínica pode vender sessões avulsas ou um novo bloco. A
     * tela mostra a marca para deixar a diferença visível.
     */
    public function excedeOPacote(): bool
    {
        return $this->pacote !== null
            && $this->pacote->total_sessoes > 0
            && $this->numero_sessao > $this->pacote->total_sessoes;
    }
}
