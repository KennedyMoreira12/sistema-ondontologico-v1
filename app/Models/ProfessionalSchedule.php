<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horário de atendimento de um profissional, por dia da semana.
 *
 * Não tem `clinica_id`: a tabela vive no banco da clínica, que JÁ é o isolamento
 * (ver `TenantModel`). Colocar a coluna aqui sugeriria que o filtro de clínica
 * acontece neste model, e não na conexão.
 *
 * `weekday` segue o `dayOfWeek` do Carbon: 0=domingo ... 6=sábado.
 */
#[Fillable(['professional_id', 'weekday', 'start_time', 'end_time', 'is_active'])]
class ProfessionalSchedule extends TenantModel
{
    protected $table = 'professional_schedules';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
