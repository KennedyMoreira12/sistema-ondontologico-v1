<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Presença de um aluno em uma turma, em uma data.
 *
 * A chave única (`turma_id`, `paciente_id`, `data`) faz a marcação ser
 * idempotente: marcar presença duas vezes no mesmo dia atualiza a linha em vez
 * de duplicar a chamada.
 */
#[Fillable(['turma_id', 'paciente_id', 'data', 'status', 'observacoes'])]
class TurmaPresenca extends TenantModel
{
    protected $table = 'turma_presencas';

    public const PRESENTE = 'presente';

    public const AUSENTE = 'ausente';

    public const REPOSICAO = 'reposicao';

    public const STATUS = [self::PRESENTE, self::AUSENTE, self::REPOSICAO];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }
}
