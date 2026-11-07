@extends('layouts.app')

@section('title', 'Relatórios — Pacotes e sessões')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Pacotes e sessões</h1>
            <p class="text-muted small mb-0">Contratado, consumido e restante por pacote e por serviço.</p>
        </div>
    </div>

    @include('relatorios.partials.nav')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('relatorios.pacotes') }}" class="row g-2 align-items-end">
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
                    <a href="{{ route('relatorios.exportar', ['tipo' => 'pacotes', 'de' => $de->format('Y-m-d'), 'ate' => $ate->format('Y-m-d')]) }}" class="btn btn-outline-success">
                        <i class="bi bi-filetype-csv me-1"></i>Exportar CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Pacotes</div>
                <div class="fs-4 fw-bold">{{ $totalPacotes }}</div>
                <div class="text-muted small">{{ $pacotesAtivos }} ativos</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary"><div class="card-body">
                <div class="text-muted small">Sessões contratadas</div>
                <div class="fs-4 fw-bold text-primary">{{ $sessoesContratadas }}</div>
                <div class="text-muted small">{{ $sessoesUsadas }} usadas no total</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success"><div class="card-body">
                <div class="text-muted small">Sessões restantes</div>
                <div class="fs-4 fw-bold text-success">{{ $sessoesRestantes }}</div>
                <div class="text-muted small">Saldo de todos os pacotes</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning"><div class="card-body">
                <div class="text-muted small">Consumidas no período</div>
                <div class="fs-4 fw-bold text-warning">{{ $consumidasNoPeriodo }}</div>
                <div class="text-muted small">{{ $reposicoesNoPeriodo }} reposições</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Consumo por serviço no período</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Serviço</th>
                        <th class="text-end">Sessões consumidas</th>
                        <th class="text-end">Reposições</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($porServico as $linha)
                        <tr>
                            <td>{{ $linha['servico'] }}</td>
                            <td class="text-end">{{ $linha['quantidade'] }}</td>
                            <td class="text-end">{{ $linha['reposicoes'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">Nenhuma sessão consumida no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Pacotes contratados</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Paciente</th>
                        <th>Pacote</th>
                        <th>Serviço</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Usadas</th>
                        <th class="text-end">Restantes</th>
                        <th>Status</th>
                        <th>Validade</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pacotes as $pacote)
                        <tr>
                            <td>{{ $pacote->paciente?->nome ?? '—' }}</td>
                            <td>{{ $pacote->nome }}</td>
                            <td>{{ $pacote->servico ?: '—' }}</td>
                            <td class="text-end">{{ $pacote->total_sessoes }}</td>
                            <td class="text-end">{{ $pacote->consumidas() }}</td>
                            <td class="text-end fw-semibold">{{ $pacote->restantes() }}</td>
                            <td>
                                <span class="badge text-bg-{{ $pacote->estaAtivo() ? 'success' : 'secondary' }}">
                                    {{ ucfirst($pacote->status) }}
                                </span>
                            </td>
                            <td>{{ $pacote->validade_em?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum pacote contratado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
