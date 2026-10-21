<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ficha de avaliação inicial da fisioterapia.
 *
 * A ficha é preenchida pelo profissional. O sistema não sugere diagnóstico
 * nem calcula conduta: os campos de texto ficam em branco até alguém digitar.
 */
#[Fillable([
    'paciente_id', 'profissional_id', 'data',
    'queixa_principal', 'historia_clinica', 'exame_fisico', 'hipotese_clinica',
    'objetivos', 'plano_terapeutico', 'observacoes',
    'status', 'finalizada_em',
])]
class Avaliacao extends TenantModel
{
    /**
     * Nome fixado: o pluralizador geraria `avaliacaos`.
     */
    protected $table = 'avaliacoes';

    public const RASCUNHO = 'rascunho';

    public const FINALIZADA = 'finalizada';

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function estaFinalizada(): bool
    {
        return $this->status === self::FINALIZADA;
    }

    public function finalize(): void
    {
        $this->forceFill([
            'status' => self::FINALIZADA,
            'finalizada_em' => now(),
        ])->save();
    }
}
