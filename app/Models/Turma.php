<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turma de Pilates (ou de outro serviço em grupo).
 *
 * A turma tem um dia da semana e um horário fixos — é a linha da grade da
 * planilha PILATES. Os alunos ficam em `turma_pacientes` e a presença de cada
 * data em `turma_presencas`, para que marcar falta hoje não apague o
 * histórico de ontem.
 */
#[Fillable([
    'nome', 'servico', 'profissional_id', 'dia_semana', 'hora_inicio',
    'hora_fim', 'capacidade', 'sala', 'is_active', 'observacoes',
])]
class Turma extends TenantModel
{
    protected $table = 'turmas';

    /**
     * Dias da semana no padrão ISO (1 = segunda ... 7 = domingo), o mesmo
     * usado por `Carbon::dayOfWeekIso`.
     *
     * @var array<int, string>
     */
    public const DIAS = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'capacidade' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'profissional_id');
    }

    public function pacientes(): BelongsToMany
    {
        return $this->belongsToMany(Paciente::class, 'turma_pacientes')
            ->withPivot(['data_inicio', 'data_fim', 'is_active'])
            ->withTimestamps();
    }

    public function presencas(): HasMany
    {
        return $this->hasMany(TurmaPresenca::class);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function diaSemanaLabel(): string
    {
        return self::DIAS[$this->dia_semana] ?? '—';
    }

    /**
     * Horário no formato HH:MM (o banco devolve HH:MM:SS).
     */
    public function horaInicioFormatada(): string
    {
        return substr((string) $this->hora_inicio, 0, 5);
    }

    public function horaFimFormatada(): ?string
    {
        return $this->hora_fim === null ? null : substr((string) $this->hora_fim, 0, 5);
    }
}
