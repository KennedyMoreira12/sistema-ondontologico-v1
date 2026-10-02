@extends('layouts.platform')

@section('title', 'Visão geral')
@section('page-title', 'Visão geral')

@section('content')
    <div class="row g-3 mb-4 sco-stat-card">
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Clínicas</div>
                    <div class="stat-value">{{ $totalClinicas }}</div>
                    <div class="text-muted small">{{ $emAvaliacao }} em avaliação</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Assinaturas ativas</div>
                    <div class="stat-value">{{ $assinaturasAtivas }}</div>
                    <div class="text-muted small">{{ $planosAtivos }} planos à venda</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Receita recorrente</div>
                    <div class="stat-value">R$ {{ number_format($mrr, 2, ',', '.') }}</div>
                    <div class="text-muted small">somente assinaturas liberando acesso</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Ações registradas</div>
                    <div class="stat-value">{{ $ultimasAcoes->count() }}</div>
                    <div class="text-muted small"><a href="{{ route('painel.auditoria.index') }}">ver auditoria</a></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Clínicas por estado</h2>
                    <ul class="list-group list-group-flush">
                        @foreach ($clinicasPorEstado as $estado => $dados)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $dados['rotulo'] }}</span>
                                <span class="badge bg-secondary">{{ $dados['total'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0">Últimas clínicas</h2>
                        <a href="{{ route('painel.clinicas.index') }}" class="btn btn-sm btn-outline-secondary">Ver todas</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Clínica</th>
                                    <th>Contato</th>
                                    <th>Estado</th>
                                    <th>Plano</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($ultimasClinicas as $clinica)
                                    <tr>
                                        <td>
                                            <a href="{{ route('painel.clinicas.show', $clinica) }}">{{ $clinica->name }}</a>
                                        </td>
                                        <td class="text-muted small">{{ $clinica->email }}</td>
                                        <td><span class="badge bg-secondary">{{ config('tenancy.clinic_states.'.$clinica->status, $clinica->status) }}</span></td>
                                        <td class="text-muted small">{{ $clinica->currentPlan()?->name ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Nenhuma clínica cadastrada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Últimas ações no painel</h2>
                    <ul class="list-unstyled mb-0">
                        @forelse ($ultimasAcoes as $acao)
                            <li class="d-flex justify-content-between align-items-start py-2 border-bottom">
                                <div>
                                    <div class="fw-semibold">{{ $acao->action }}</div>
                                    <div class="text-muted small">
                                        {{ $acao->adminUser?->name ?? 'sistema' }}
                                        @if ($acao->clinic)
                                            — {{ $acao->clinic->name }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-muted small text-nowrap">{{ $acao->created_at?->format('d/m/Y H:i') }}</div>
                            </li>
                        @empty
                            <li class="text-muted small py-3">Nenhuma ação registrada ainda.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
