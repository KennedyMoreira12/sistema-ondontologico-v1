@extends('layouts.app')

@section('title', 'Agenda do dia')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Agenda do dia</h1>
            <p class="text-muted small mb-0">{{ $dia->translatedFormat('l, d \d\e F \d\e Y') }}</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('agenda.dia') }}" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="data" value="{{ $dia->toDateString() }}">
                <select name="profissional_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Todos os profissionais</option>
                    @foreach ($profissionais as $profissional)
                        <option value="{{ $profissional->id }}" @selected($profissionalId === $profissional->id)>{{ $profissional->nome }}</option>
                    @endforeach
                </select>
            </form>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('agenda.dia', ['data' => $dia->copy()->subDay()->toDateString()]) }}" class="btn btn-outline-secondary">&laquo;</a>
                <a href="{{ route('agenda.dia') }}" class="btn btn-outline-secondary">Hoje</a>
                <a href="{{ route('agenda.dia', ['data' => $dia->copy()->addDay()->toDateString()]) }}" class="btn btn-outline-secondary">&raquo;</a>
            </div>
            @can('agenda.create')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#agendarModal">
                    <i class="bi bi-plus-lg me-1"></i>Agendar
                </button>
            @endcan
        </div>
    </div>

    <ul class="nav nav-pills gap-1 mb-3">
        <li class="nav-item"><a class="nav-link" href="{{ route('agenda.index', ['data' => $dia->toDateString()]) }}">Semana</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('agenda.dia', ['data' => $dia->toDateString()]) }}">Dia</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('agenda.mes', ['data' => $dia->toDateString()]) }}">Mês</a></li>
    </ul>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Atendimentos</div><div class="fs-4 fw-bold">{{ $resumo['total'] }}</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Aguardando</div><div class="fs-4 fw-bold text-primary">{{ $resumo['aguardando'] }}</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Atendidos</div><div class="fs-4 fw-bold text-success">{{ $resumo['atendidos'] }}</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Faltas</div><div class="fs-4 fw-bold text-danger">{{ $resumo['faltas'] }}</div></div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Serviço</th>
                        <th>Profissional</th>
                        <th>Sala</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($consultas as $consulta)
                        @php
                            $nomeStatus = $consulta->status?->name;
                            $podePresenca = in_array($nomeStatus, ['agendado', 'confirmado', 'em_atendimento'], true);
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $consulta->inicio->format('H:i') }}<div class="text-muted small">{{ $consulta->inicio->diffInMinutes($consulta->fim) }} min</div></td>
                            <td>
                                <div class="fw-semibold">{{ $consulta->paciente?->nome ?? '—' }}</div>
                                @if ($consulta->titulo)
                                    <div class="text-muted small">{{ $consulta->titulo }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $consulta->servico ?: '—' }}
                                @if ($consulta->area)
                                    <div class="text-muted small">{{ $consulta->area }}</div>
                                @endif
                                @if ($consulta->valor)
                                    <div class="text-muted small">R$ {{ number_format((float) $consulta->valor, 2, ',', '.') }}</div>
                                @endif
                            </td>
                            <td>{{ $consulta->profissional?->nome ?? '—' }}</td>
                            <td>{{ $consulta->room?->nome ?? '—' }}</td>
                            <td><span class="badge" style="background: {{ $consulta->status?->cor ?? '#6c757d' }}">{{ $consulta->status?->label ?? '—' }}</span></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1 flex-wrap">
                                    @can('agenda.update')
                                        @if ($podePresenca)
                                            <form method="POST" action="{{ route('agenda.presenca', $consulta) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="atendido">
                                                <button class="btn btn-sm btn-success" title="Registrar presença"><i class="bi bi-check-lg"></i> Presente</button>
                                            </form>
                                            <form method="POST" action="{{ route('agenda.presenca', $consulta) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="faltou">
                                                <button class="btn btn-sm btn-outline-danger" title="Registrar falta"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        @endif
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#reagendar-{{ $consulta->id }}" title="Reagendar"><i class="bi bi-calendar-event"></i></button>
                                    @endcan
                                    @if ($consulta->paciente)
                                        <a href="{{ route('pacientes.historico', $consulta->paciente) }}" class="btn btn-sm btn-outline-primary" title="Histórico do paciente"><i class="bi bi-clock-history"></i></a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @can('agenda.update')
                            <tr class="collapse" id="reagendar-{{ $consulta->id }}">
                                <td colspan="7" class="bg-light">
                                    <form method="POST" action="{{ route('agenda.reagendar', $consulta) }}" class="row g-2 align-items-end">
                                        @csrf
                                        @method('PATCH')
                                        <div class="col-md-2">
                                            <label class="form-label">Data</label>
                                            <input type="date" name="dia" value="{{ $consulta->inicio->toDateString() }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Hora</label>
                                            <input type="time" name="hora_inicio" value="{{ $consulta->inicio->format('H:i') }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Duração</label>
                                            <select name="duracao" class="form-select form-select-sm">
                                                @foreach ([15, 30, 45, 60, 90, 120] as $min)
                                                    <option value="{{ $min }}" @selected($consulta->inicio->diffInMinutes($consulta->fim) == $min)>{{ $min }} min</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Profissional</label>
                                            <select name="profissional_id" class="form-select form-select-sm">
                                                @foreach ($profissionais as $profissional)
                                                    <option value="{{ $profissional->id }}" @selected($consulta->profissional_id === $profissional->id)>{{ $profissional->nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Sala</label>
                                            <select name="room_id" class="form-select form-select-sm">
                                                <option value="">Sem sala</option>
                                                @foreach ($salas as $sala)
                                                    <option value="{{ $sala->id }}" @selected($consulta->room_id === $sala->id)>{{ $sala->nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-grid">
                                            <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Reagendar</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endcan
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum atendimento para este dia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('agenda.create')
        @include('agenda.partials.create-modal')
    @endcan
@endsection
