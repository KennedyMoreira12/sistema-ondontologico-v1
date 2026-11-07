@extends('layouts.app')

@section('title', 'Agenda do mês')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Agenda do mês</h1>
            <p class="text-muted small mb-0">{{ $referencia->translatedFormat('F \d\e Y') }}</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('agenda.mes') }}" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="data" value="{{ $referencia->toDateString() }}">
                <select name="profissional_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Todos os profissionais</option>
                    @foreach ($profissionais as $profissional)
                        <option value="{{ $profissional->id }}" @selected($profissionalId === $profissional->id)>{{ $profissional->nome }}</option>
                    @endforeach
                </select>
            </form>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('agenda.mes', ['data' => $referencia->copy()->subMonthNoOverflow()->toDateString()]) }}" class="btn btn-outline-secondary">&laquo;</a>
                <a href="{{ route('agenda.mes') }}" class="btn btn-outline-secondary">Hoje</a>
                <a href="{{ route('agenda.mes', ['data' => $referencia->copy()->addMonthNoOverflow()->toDateString()]) }}" class="btn btn-outline-secondary">&raquo;</a>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills gap-1 mb-3">
        <li class="nav-item"><a class="nav-link" href="{{ route('agenda.index', ['data' => $referencia->toDateString()]) }}">Semana</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('agenda.dia', ['data' => $referencia->toDateString()]) }}">Dia</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('agenda.mes', ['data' => $referencia->toDateString()]) }}">Mês</a></li>
    </ul>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-bordered align-top mb-0 calendar-table">
                <thead class="table-light">
                    <tr>
                        @foreach (['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'] as $semana)
                            <th class="text-center">{{ $semana }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php $cursor = $inicio->copy(); @endphp
                    @while ($cursor->lte($fim))
                        <tr>
                            @for ($i = 0; $i < 7; $i++)
                                @php
                                    $doDia = $porDia->get($cursor->toDateString(), collect());
                                    $foraDoMes = ! $cursor->isCurrentMonth();
                                @endphp
                                <td class="calendar-day {{ $foraDoMes ? 'bg-light text-muted' : '' }} {{ $cursor->isToday() ? 'table-primary' : '' }}" style="width: 14.28%; min-height: 110px;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <a href="{{ route('agenda.dia', ['data' => $cursor->toDateString()]) }}" class="fw-semibold text-decoration-none">{{ $cursor->day }}</a>
                                        @if ($doDia->isNotEmpty())
                                            <span class="badge text-bg-secondary">{{ $doDia->count() }}</span>
                                        @endif
                                    </div>
                                    @foreach ($doDia->take(3) as $consulta)
                                        <div class="small text-truncate" title="{{ $consulta->inicio->format('H:i') }} — {{ $consulta->paciente?->nome }}">
                                            <span class="text-muted">{{ $consulta->inicio->format('H:i') }}</span>
                                            {{ $consulta->paciente?->nome ?? '—' }}
                                        </div>
                                    @endforeach
                                    @if ($doDia->count() > 3)
                                        <div class="small text-muted">+ {{ $doDia->count() - 3 }} atendimento(s)</div>
                                    @endif
                                </td>
                                @php $cursor->addDay(); @endphp
                            @endfor
                        </tr>
                    @endwhile
                </tbody>
            </table>
        </div>
    </div>
@endsection
