<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'clinica_id', 'paciente_id', 'profissional_id', 'room_id', 'appointment_status_id',
    'inicio', 'fim', 'titulo', 'observacao', 'retorno', 'idempotency_key',
    'area', 'servico', 'valor',
])]
class Appointment extends TenantModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'retorno' => 'boolean',
            'valor' => 'decimal:2',
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AppointmentStatus::class, 'appointment_status_id');
    }

    /**
     * Sessões de pacote debitadas por este atendimento.
     */
    public function consumos(): HasMany
    {
        return $this->hasMany(ConsumoSessao::class, 'appointment_id');
    }

    public function foiAtendido(): bool
    {
        return $this->status?->name === 'atendido';
    }

    public function faltou(): bool
    {
        return $this->status?->name === 'faltou';
    }
}
