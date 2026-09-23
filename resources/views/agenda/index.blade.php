@extends('layouts.app')

@section('title', 'Agenda')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Agenda</h1>
            <p class="text-muted small mb-0">Semana de {{ $inicioSemana->translatedFormat('d/m/Y') }} a {{ $dias[5]->translatedFormat('d/m/Y') }}</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" action="{{ route('agenda.index') }}" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="data" value="{{ $inicioSemana->toDateString() }}">
                <select name="profissional_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Todos os profissionais</option>
                    @foreach ($profissionais as $profissional)
                        <option value="{{ $profissional->id }}" @selected($profissionalId === $profissional->id)>{{ $profissional->nome }}</option>
                    @endforeach
                </select>
            </form>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('agenda.index', ['data' => $inicioSemana->copy()->subWeek()->toDateString()]) }}" class="btn btn-outline-secondary">&laquo;</a>
                <a href="{{ route('agenda.index') }}" class="btn btn-outline-secondary">Hoje</a>
                <a href="{{ route('agenda.index', ['data' => $inicioSemana->copy()->addWeek()->toDateString()]) }}" class="btn btn-outline-secondary">&raquo;</a>
            </div>
            @can('agenda.create')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#agendarModal">
                    <i class="bi bi-plus-lg me-1"></i>Agendar
                </button>
            @endcan
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle table-striped schedule-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center time-col">Hora</th>
                                @foreach ($dias as $dia)
                                    <th class="text-center {{ $dia->isToday() ? 'table-primary' : '' }}">
                                        {{ $dia->translatedFormat('D') }}<br>
                                        <span class="fs-6 fw-bold">{{ $dia->translatedFormat('d/m') }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($horarios as $hora)
                                <tr>
                                    <td class="text-center text-muted small time-col">{{ $hora }}</td>
                                    @foreach ($dias as $dia)
                                        @php
                                            $celula = $consultas->get($dia->toDateString().'|'.$hora, collect());
                                        @endphp
                                        <td class="p-1" style="min-width: 150px;">
                                            @foreach ($celula as $consulta)
                                                @include('agenda.partials.appointment-card')
                                            @endforeach
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-hourglass-split me-1"></i>Lista de espera</span>
                    <span class="badge bg-warning text-dark">{{ $listaEspera->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($listaEspera as $entry)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold d-flex align-items-center gap-2">
                                            {{ $entry->paciente?->nome ?? 'Paciente removido' }}
                                            <span class="badge {{ $entry->prioridade <= 2 ? 'bg-danger' : 'bg-secondary' }}">{{ $entry->prioridade }}</span>
                                        </div>
                                        <div class="text-muted small">
                                            Desde {{ $entry->periodo_inicio->translatedFormat('d/m') }}
                                            @if ($entry->periodo_fim)
                                                até {{ $entry->periodo_fim->translatedFormat('d/m') }}
                                            @endif
                                        </div>
                                        @if ($entry->motivo)
                                            <div class="small text-muted">{{ $entry->motivo }}</div>
                                        @endif
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <form method="POST" action="{{ route('agenda.waiting.update', $entry) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="atendido">
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-check-lg me-2"></i>Atendido</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" action="{{ route('agenda.waiting.destroy', $entry) }}" onsubmit="return confirm('Remover da lista de espera?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Remover</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted py-4">Lista de espera vazia.</li>
                        @endforelse
                    </ul>
                </div>
                @can('agenda.create')
                    <div class="card-footer bg-white">
                        <form method="POST" action="{{ route('agenda.waiting.store') }}">
                            @csrf
                            <div class="mb-2">
                                <select name="paciente_id" class="form-select form-select-sm" required>
                                    <option value="">Paciente...</option>
                                    @foreach ($pacientes as $paciente)
                                        <option value="{{ $paciente->id }}">{{ $paciente->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-4">
                                    <select name="prioridade" class="form-select form-select-sm" title="Prioridade">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <option value="{{ $i }}" @selected($i === 3)>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-4">
                                    <input type="date" name="periodo_inicio" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required>
                                </div>
                                <div class="col-4">
                                    <input type="date" name="periodo_fim" class="form-control form-control-sm" placeholder="Fim">
                                </div>
                            </div>
                            <div class="mb-2">
                                <input type="text" name="motivo" class="form-control form-control-sm" placeholder="Motivo (opcional)">
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-success w-100">
                                <i class="bi bi-plus-lg me-1"></i>Adicionar à espera
                            </button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>
    </div>

    @can('agenda.create')
        @include('agenda.partials.create-modal')
    @endcan

    @can('agenda.update')
        @include('agenda.partials.edit-modal')
    @endcan
@endsection

@push('scripts')
    <script>
        @if ($errors->any())
            document.addEventListener('DOMContentLoaded', function () {
                new bootstrap.Modal(document.getElementById('agendarModal')).show();
            });
        @endif

        @if (!$errors->any())
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-consulta]').forEach(function (card) {
                    card.addEventListener('click', function () {
                        const data = JSON.parse(card.dataset.consulta);

                        document.getElementById('editPaciente').value = data.paciente_id;
                        document.getElementById('editProfissional').value = data.profissional_id;
                        document.getElementById('editSala').value = data.room_id || '';
                        document.getElementById('editStatus').value = data.status;
                        document.getElementById('editDia').value = data.dia;
                        document.getElementById('editHora').value = data.hora;
                        document.getElementById('editDuracao').value = data.duracao;
                        document.getElementById('editTitulo').value = data.titulo || '';
                        document.getElementById('editObservacao').value = data.observacao || '';
                        document.getElementById('editRetorno').checked = data.retorno;
                        document.getElementById('editForm').action = data.url_update;
                        document.getElementById('editCancelForm').action = data.url_destroy;
                        document.getElementById('editPacienteNome').textContent = data.paciente;

                        window.editarConsultaModal = new bootstrap.Modal(document.getElementById('editModal')).show();
                    });
                });
            });
        @endif
    </script>
@endpush