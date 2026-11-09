@extends('layouts.app')

@section('title', 'Relatórios — Presença e faltas')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Presença e faltas</h1>
            <p class="text-muted small mb-0">Atendimentos do período por status, profissional e mês.</p>
        </div>
    </div>

    @include('relatorios.partials.nav')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('relatorios.presenca-faltas') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="de" class="form-label">De</label>
                    <input type="date" id="de" name="de" class="form-control" value="{{ $de->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label for="ate" class="form-label">Até</label>
                    <input type="date" id="ate" name="ate" class="form-control" value="{{ $ate->format('Y-m-d') }}">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
                <div class="col-md-4 d-grid">
                    <a href="{{ route('relatorios.exportar', ['tipo' => 'presenca', 'de' => $de->format('Y-m-d'), 'ate' => $ate->format('Y-m-d')]) }}" class="btn btn-outline-success">
                        <i class="bi bi-filetype-csv me-1"></i>Exportar CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Atendimentos</div>
                <div class="fs-4 fw-bold">{{ $total }}</div>
                <div class="text-muted small">{{ $aguardando }} aguardando</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success"><div class="card-body">
                <div class="text-muted small">Presenças</div>
                <div class="fs-4 fw-bold text-success">{{ $atendidos }}</div>
                <div class="text-muted small">Status atendido</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger"><div class="card-body">
                <div class="text-muted small">Faltas</div>
                <div class="fs-4 fw-bold text-danger">{{ $faltas }}</div>
                <div class="text-muted small">{{ $cancelados }} cancelados</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning"><div class="card-body">
                <div class="text-muted small">Taxa de faltas</div>
                <div class="fs-4 fw-bold text-warning">{{ number_format($taxaFaltas, 1, ',', '.') }}%</div>
                <div class="text-muted small">Faltas sobre o total</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Por profissional</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Profissional</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Presenças</th>
                                <th class="text-end">Faltas</th>
                                <th class="text-end">Taxa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($porProfissional as $linha)
                                <tr>
                                    <td>{{ $linha['profissional'] }}</td>
                                    <td class="text-end">{{ $linha['total'] }}</td>
                                    <td class="text-end text-success">{{ $linha['atendidos'] }}</td>
                                    <td class="text-end text-danger">{{ $linha['faltas'] }}</td>
                                    <td class="text-end">{{ number_format($linha['taxa_faltas'], 1, ',', '.') }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Nenhum atendimento no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Por mês</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mês</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Presenças</th>
                                <th class="text-end">Faltas</th>
                                <th class="text-end">Taxa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($porMes as $linha)
                                <tr>
                                    <td>{{ $linha['mes'] }}</td>
                                    <td class="text-end">{{ $linha['total'] }}</td>
                                    <td class="text-end text-success">{{ $linha['atendidos'] }}</td>
                                    <td class="text-end text-danger">{{ $linha['faltas'] }}</td>
                                    <td class="text-end">{{ number_format($linha['taxa_faltas'], 1, ',', '.') }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Nenhum atendimento no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Por área</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Área</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Presenças</th>
                        <th class="text-end">Faltas</th>
                        <th class="text-end">Taxa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($porArea as $linha)
                        <tr>
                            <td>{{ $linha['area'] }}</td>
                            <td class="text-end">{{ $linha['total'] }}</td>
                            <td class="text-end text-success">{{ $linha['atendidos'] }}</td>
                            <td class="text-end text-danger">{{ $linha['faltas'] }}</td>
                            <td class="text-end">{{ number_format($linha['taxa_faltas'], 1, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Nenhum atendimento no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Atendimentos do período</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Profissional</th>
                        <th>Serviço</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($consultas as $consulta)
                        <tr>
                            <td>{{ $consulta->inicio->format('d/m/Y') }}</td>
                            <td>{{ $consulta->inicio->format('H:i') }}</td>
                            <td>{{ $consulta->paciente?->nome ?? '—' }}</td>
                            <td>{{ $consulta->profissional?->nome ?? '—' }}</td>
                            <td>
                                {{ $consulta->servico ?: ($consulta->titulo ?: '—') }}
                                @if ($consulta->area)
                                    <div class="text-muted small">{{ $consulta->area }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge" style="background: {{ $consulta->status?->cor ?? '#6c757d' }}">
                                    {{ $consulta->status?->label ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum atendimento no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
