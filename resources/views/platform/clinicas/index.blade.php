@extends('layouts.platform')

@section('title', 'Clínicas')
@section('page-title', 'Clínicas')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Clínicas</h1>
            <p class="text-muted small mb-0">Cada clínica tem banco de dados próprio e assinatura própria.</p>
        </div>
        @if (auth()->user()->isSuperAdmin())
            <a href="{{ route('painel.clinicas.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nova clínica
            </a>
        @endif
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="busca" class="form-label small text-muted">Buscar</label>
                    <input id="busca" type="search" class="form-control" name="busca" value="{{ $busca }}" placeholder="Nome, e-mail ou CNPJ">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small text-muted">Estado</label>
                    <select id="status" class="form-select" name="status">
                        <option value="">Todos</option>
                        @foreach ($estados as $chave => $rotulo)
                            <option value="{{ $chave }}" @selected($status === $chave)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filtrar</button>
                    @if ($busca || $status)
                        <a href="{{ route('painel.clinicas.index') }}" class="btn btn-outline-secondary">Limpar</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Clínica</th>
                            <th>Estado</th>
                            <th>Plano atual</th>
                            <th>Vencimento</th>
                            <th>Banco</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clinicas as $clinica)
                            <tr>
                                <td>
                                    <a href="{{ route('painel.clinicas.show', $clinica) }}" class="fw-semibold text-decoration-none">{{ $clinica->name }}</a>
                                    <div class="text-muted small">{{ $clinica->email }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $clinica->isAccessible() ? 'success' : 'secondary' }}">
                                        {{ $estados[$clinica->status] ?? $clinica->status }}
                                    </span>
                                    @unless ($clinica->tenantConnection?->is_active ?? true)
                                        <span class="badge bg-danger">Conexão desligada</span>
                                    @endunless
                                </td>
                                <td>{{ $clinica->currentPlan()?->name ?? '—' }}</td>
                                <td class="text-muted small">
                                    @if ($clinica->latestSubscription?->current_period_end)
                                        {{ $clinica->latestSubscription->current_period_end->format('d/m/Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-muted small font-monospace">{{ $clinica->tenantConnection?->database_name ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('painel.clinicas.show', $clinica) }}" class="btn btn-sm btn-outline-secondary" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>
                                    <a href="{{ route('painel.clinicas.edit', $clinica) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Nenhuma clínica encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $clinicas->links() }}
            </div>
        </div>
    </div>
@endsection
