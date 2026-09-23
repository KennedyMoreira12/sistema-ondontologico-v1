@php
    $card = [
        'paciente_id' => $consulta->paciente_id,
        'profissional_id' => $consulta->profissional_id,
        'room_id' => $consulta->room_id,
        'status' => $consulta->appointment_status_id,
        'dia' => $consulta->inicio->toDateString(),
        'hora' => $consulta->inicio->format('H:i'),
        'duracao' => $consulta->inicio->diffInMinutes($consulta->fim),
        'titulo' => $consulta->titulo,
        'observacao' => $consulta->observacao,
        'retorno' => $consulta->retorno,
        'paciente' => $consulta->paciente?->nome ?? '—',
        'url_update' => route('agenda.update', $consulta),
        'url_destroy' => route('agenda.destroy', $consulta),
    ];
@endphp
<button
    type="button"
    class="btn btn-sm text-start w-100 mb-1 appointment-card"
    style="border-left: 4px solid {{ $consulta->status?->cor ?? '#6c757d' }}; background: #fff;"
    data-bs-toggle="{{ auth()->user()->hasPermission('agenda.update') ? 'modal' : '' }}"
    data-bs-target="{{ auth()->user()->hasPermission('agenda.update') ? '#editModal' : '' }}"
    data-consulta="{{ json_encode($card) }}"
>
    <span class="small fw-semibold text-dark d-block">
        {{ $consulta->inicio->format('H:i') }} – {{ $consulta->fim->format('H:i') }}
        @if ($consulta->retorno)
            <i class="bi bi-arrow-repeat text-warning" title="Retorno"></i>
        @endif
    </span>
    <span class="small text-dark d-block text-truncate">{{ $consulta->paciente?->nome ?? 'Paciente removido' }}</span>
    <span class="small text-muted d-block text-truncate">
        {{ $consulta->profissional?->nome }}@if ($consulta->room?->nome) • {{ $consulta->room->nome }}@endif
    </span>
</button>