<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Evolução de UMA sessão de fisioterapia.
 *
 * Nasce em rascunho, que é editável, e vira definitiva quando o profissional
 * assina. Definitiva é imutável: é o que dá peso à auditoria — um registro
 * clínico assinado não muda por baixo dos panos.
 */
#[Fillable([
    'paciente_id', 'profissional_id', 'avaliacao_id', 'appointment_id', 'data',
    'conteudo', 'tecnicas', 'resposta_paciente', 'dor_antes', 'dor_depois',
    'status', 'finalizada_em',
])]
class EvolucaoSessao extends TenantModel
{
    /**
     * Nome fixado: o pluralizador geraria `evolucao_sessaos`.
     */
    protected $table = 'evolucoes_sessao';

    public const RASCUNHO = 'rascunho';

    public const FINALIZADA = 'finalizada';

    /**
     * `data` e `finalizada_em` são datas: sem o cast as views chamam
     * `->format()` em string e a tela estoura 500.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'finalizada_em' => 'datetime',
            'dor_antes' => 'integer',
            'dor_depois' => 'integer',
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

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class);
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(ConsumoSessao::class);
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
