@extends('layouts.app')

@section('title', 'Atendimentos do dia')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Atendimentos do dia</h1>
            <p class="text-muted small mb-0">{{ $dia->translatedFormat('l, d \d\e F \d\e Y') }} — presença, falta e consumo de sessão.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('atendimentos.index') }}" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="data" value="{{ $dia->toDateString() }}">
                <select name="profissional_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Todos os profissionais</option>
                    @foreach ($profissionais as $profissional)
                        <option value="{{ $profissional->id }}" @selected($profissionalId === $profissional->id)>{{ $profissional->nome }}</option>
                    @endforeach
                </select>
            </form>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('atendimentos.index', ['data' => $dia->copy()->subDay()->toDateString()]) }}" class="btn btn-outline-secondary">&laquo;</a>
                <a href="{{ route('atendimentos.index') }}" class="btn btn-outline-secondary">Hoje</a>
                <a href="{{ route('atendimentos.index', ['data' => $dia->copy()->addDay()->toDateString()]) }}" class="btn btn-outline-secondary">&raquo;</a>
            </div>
            <a href="{{ route('agenda.dia', ['data' => $dia->toDateString()]) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-calendar3 me-1"></i>Ver agenda
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Atendimentos</div>
                <div class="fs-4 fw-bold">{{ $resumo['total'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success"><div class="card-body">
                <div class="text-muted small">Presentes</div>
                <div class="fs-4 fw-bold text-success">{{ $resumo['presentes'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger"><div class="card-body">
                <div class="text-muted small">Faltas</div>
                <div class="fs-4 fw-bold text-danger">{{ $resumo['faltas'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning"><div class="card-body">
                <div class="text-muted small">Presentes sem pacote debitado</div>
                <div class="fs-4 fw-bold text-warning">{{ $resumo['sem_pacote'] }}</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Serviço / área</th>
                        <th>Profissional</th>
                        <th>Pacote</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($consultas as $consulta)
                        @php
                            $nomeStatus = $consulta->status?->name;
                            $podePresenca = in_array($nomeStatus, ['agendado', 'confirmado', 'em_atendimento'], true);
                            $consumo = $consumos->get($consulta->id);
                            $pacotesDoPaciente = $pacotes->get($consulta->paciente_id, collect());
                        @endphp
                        <tr>
                            <td class="fw-semibold">
                                {{ $consulta->inicio->format('H:i') }}
                                <div class="text-muted small">{{ $consulta->inicio->diffInMinutes($consulta->fim) }} min</div>
                            </td>
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
                            <td>
                                @if ($consumo)
                                    <span class="badge text-bg-success"><i class="bi bi-check-lg me-1"></i>Sessão debitada</span>
                                    @if ($consumo->pacote)
                                        <div class="text-muted small">{{ $consumo->pacote->nome }}</div>
                                    @endif
                                @elseif ($pacotesDoPaciente->isNotEmpty())
                                    <div class="d-flex flex-column gap-1">
                                        @foreach ($pacotesDoPaciente as $pacote)
                                            <span class="small">{{ $pacote->nome }}: {{ $pacote->restantes() }}/{{ $pacote->total_sessoes }} restantes</span>
                                        @endforeach
                                    </div>
                                @elseif ($nomeStatus === 'atendido')
                                    <span class="badge text-bg-warning">Sem pacote ativo</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge" style="background: {{ $consulta->status?->cor ?? '#6c757d' }}">
                                    {{ $consulta->status?->label ?? '—' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1 flex-wrap">
                                    @can('agenda.update')
                                        @if ($podePresenca)
                                            <form method="POST" action="{{ route('agenda.presenca', $consulta) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="atendido">
                                                <button class="btn btn-sm btn-success" title="Registrar presença"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form method="POST" action="{{ route('agenda.presenca', $consulta) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="faltou">
                                                <button class="btn btn-sm btn-outline-danger" title="Registrar falta"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        @endif

                                        @if ($nomeStatus === 'atendido' && ! $consumo && $pacotesDoPaciente->isNotEmpty())
                                            <form method="POST" action="{{ route('atendimentos.consumir', $consulta) }}" class="d-flex align-items-center gap-1">
                                                @csrf
                                                <select name="pacote_id" class="form-select form-select-sm" style="min-width: 180px;" required>
                                                    @foreach ($pacotesDoPaciente as $pacote)
                                                        <option value="{{ $pacote->id }}">
                                                            {{ $pacote->nome }} ({{ $pacote->restantes() }} restantes)
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="form-check ms-1" title="Reposição de falta">
                                                    <input class="form-check-input" type="checkbox" name="reposicao" value="1" id="reposicao-{{ $consulta->id }}">
                                                    <label class="form-check-label small" for="reposicao-{{ $consulta->id }}">Rep.</label>
                                                </div>
                                                <button class="btn btn-sm btn-outline-success" title="Consumir sessão"><i class="bi bi-box-arrow-down"></i></button>
                                            </form>
                                        @endif
                                    @endcan

                                    @if ($consulta->paciente)
                                        <a href="{{ route('pacientes.historico', $consulta->paciente) }}" class="btn btn-sm btn-outline-primary" title="Histórico do paciente">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum atendimento para este dia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
