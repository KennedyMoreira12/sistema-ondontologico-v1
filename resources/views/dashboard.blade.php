<x-app-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Dashboard</h1>
            <p class="text-muted small mb-0">Visão geral da clínica.</p>
        </div>
    </div>

    <div class="row g-3 mb-4 sco-stat-card">
        @can('agenda.view')
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="stat-icon"><i class="bi bi-calendar2-check"></i></span>
                            <div>
                                <div class="stat-value">{{ $consultasHoje->count() }}</div>
                                <div class="stat-label">Consultas hoje</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        @can('pacientes.view')
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="stat-icon"><i class="bi bi-people"></i></span>
                            <div>
                                <div class="stat-value">{{ $pacientes }}</div>
                                <div class="stat-label">Pacientes cadastrados</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        @can('tratamentos.view')
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="stat-icon"><i class="bi bi-clipboard2-pulse"></i></span>
                            <div>
                                <div class="stat-value">{{ $tratamentosEmAndamento }}</div>
                                <div class="stat-label">Tratamentos em andamento</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        @can('estoque.view')
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="stat-icon"><i class="bi bi-box-seam"></i></span>
                            <div>
                                <div class="stat-value">{{ $estoqueBaixo }}</div>
                                <div class="stat-label">Produtos com estoque baixo</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endcan
    </div>

    <div class="row g-4">
        @can('agenda.view')
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Agenda de hoje</span>
                        <span class="text-muted small">{{ now()->translatedFormat('d/m/Y \\- l') }}</span>
                    </div>
                    <div class="card-body p-0">
                        @if ($consultasHoje->isEmpty())
                            <div class="p-4 text-center text-muted">Nenhuma consulta para hoje.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Horário</th>
                                            <th>Paciente</th>
                                            <th>Profissional</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($consultasHoje as $consulta)
                                            <tr>
                                                <td>{{ $consulta->inicio->format('H:i') }}</td>
                                                <td>{{ $consulta->paciente?->nome }}</td>
                                                <td>{{ $consulta->profissional?->nome }}</td>
                                                <td>
                                                    <span class="badge" style="background-color: {{ $consulta->status?->cor ?? '#6c757d' }};">
                                                        {{ $consulta->status?->label ?? '—' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endcan

        @can('financeiro.view')
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <span class="fw-semibold">Contas a receber em aberto</span>
                    </div>
                    <div class="card-body p-0">
                        @if ($contasReceber->isEmpty())
                            <div class="p-4 text-center text-muted">Nenhuma conta em aberto.</div>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach ($contasReceber->take(8) as $conta)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>{{ $conta->paciente?->nome }}</span>
                                        <span class="text-danger fw-semibold">R$ {{ number_format($conta->saldo, 2, ',', '.') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        @endcan
    </div>
</x-app-layout>