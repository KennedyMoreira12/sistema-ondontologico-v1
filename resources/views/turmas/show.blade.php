@extends('layouts.app')

@section('title', 'Turma de Pilates')

@section('page-title', 'Turma de Pilates')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('turmas.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">{{ $turma->nome }}</h1>
                <p class="text-muted small mb-0">
                    {{ $turma->diaSemanaLabel() }} · {{ $turma->horaInicioFormatada() }}
                    @if ($turma->horaFimFormatada())– {{ $turma->horaFimFormatada() }}@endif
                    · {{ $turma->profissional?->nome ?? 'Sem profissional' }}
                </p>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('pacotes.delete')
                <form method="POST" action="{{ route('turmas.destroy', $turma) }}" onsubmit="return confirm('Encerrar esta turma? O histórico de presenças será preservado.');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Encerrar turma</button>
                </form>
            @endcan
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="bi bi-people me-2"></i>Alunos da turma</span>
                    <form method="GET" action="{{ route('turmas.show', $turma) }}" class="d-flex gap-2 align-items-center">
                        <label for="data" class="form-label mb-0 small text-muted">Lista de presença de</label>
                        <input type="date" id="data" name="data" value="{{ $data->toDateString() }}" class="form-control form-control-sm">
                        <button class="btn btn-outline-primary btn-sm">Ver</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Aluno</th>
                                <th>Situação em {{ $data->format('d/m/Y') }}</th>
                                <th class="text-end">Marcar presença</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($alunosAtivos as $paciente)
                                @php($presenca = $presencas->get($paciente->id))
                                <tr>
                                    <td class="fw-semibold">{{ $paciente->nome }}</td>
                                    <td>
                                        @if ($presenca)
                                            <span class="badge text-bg-{{ $presenca->status === 'presente' ? 'success' : ($presenca->status === 'ausente' ? 'danger' : 'info') }}">
                                                {{ ucfirst($presenca->status) }}
                                            </span>
                                        @else
                                            <span class="badge text-bg-light border">Sem registro</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @can('pacotes.update')
                                            <form method="POST" action="{{ route('turmas.presencas.store', $turma) }}" class="d-inline-flex gap-1">
                                                @csrf
                                                <input type="hidden" name="paciente_id" value="{{ $paciente->id }}">
                                                <input type="hidden" name="data" value="{{ $data->toDateString() }}">
                                                <select name="status" class="form-select form-select-sm" style="width: 120px;">
                                                    @foreach (\App\Models\TurmaPresenca::STATUS as $status)
                                                        <option value="{{ $status }}" @selected($presenca?->status === $status)>{{ ucfirst($status) }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-sm btn-outline-primary" title="Registrar"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Nenhum aluno vinculado a esta turma.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Histórico de presenças</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Data</th><th>Aluno</th><th>Situação</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($historico as $dia => $presencasDoDia)
                                @foreach ($presencasDoDia as $presenca)
                                    <tr>
                                        <td>{{ $presenca->data->format('d/m/Y') }}</td>
                                        <td>{{ $presenca->paciente?->nome ?? '—' }}</td>
                                        <td>
                                            <span class="badge text-bg-{{ $presenca->status === 'presente' ? 'success' : ($presenca->status === 'ausente' ? 'danger' : 'info') }}">
                                                {{ ucfirst($presenca->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Nenhuma presença registrada ainda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>Dados da turma</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Dia</dt>
                        <dd class="col-7">{{ $turma->diaSemanaLabel() }}</dd>
                        <dt class="col-5">Horário</dt>
                        <dd class="col-7">{{ $turma->horaInicioFormatada() }}@if ($turma->horaFimFormatada()) – {{ $turma->horaFimFormatada() }}@endif</dd>
                        <dt class="col-5">Serviço</dt>
                        <dd class="col-7">{{ $turma->servico }}</dd>
                        <dt class="col-5">Profissional</dt>
                        <dd class="col-7">{{ $turma->profissional?->nome ?? '—' }}</dd>
                        <dt class="col-5">Sala</dt>
                        <dd class="col-7">{{ $turma->sala ?: '—' }}</dd>
                        <dt class="col-5">Capacidade</dt>
                        <dd class="col-7">{{ $turma->capacidade ?? '—' }}</dd>
                        <dt class="col-5">Alunos</dt>
                        <dd class="col-7">{{ $alunosAtivos->count() }}</dd>
                        <dt class="col-5">Observações</dt>
                        <dd class="col-7">{{ $turma->observacoes ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            @can('pacotes.update')
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-plus me-2"></i>Adicionar aluno</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('turmas.pacientes.store', $turma) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="paciente_id" class="form-label">Paciente</label>
                                <select id="paciente_id" name="paciente_id" class="form-select @error('paciente_id') is-invalid @enderror" required>
                                    <option value="">Selecione</option>
                                    @foreach ($pacientesDisponiveis as $paciente)
                                        <option value="{{ $paciente->id }}" @selected(old('paciente_id') == $paciente->id)>{{ $paciente->nome }}</option>
                                    @endforeach
                                </select>
                                @error('paciente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="data_inicio" class="form-label">Início</label>
                                <input type="date" id="data_inicio" name="data_inicio" class="form-control" value="{{ old('data_inicio', now()->toDateString()) }}">
                            </div>
                            <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Adicionar à turma</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
