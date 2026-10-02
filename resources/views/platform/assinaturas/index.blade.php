@extends('layouts.platform')

@section('title', 'Assinaturas')
@section('page-title', 'Assinaturas')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0">Assinaturas</h1>
            <p class="text-muted small mb-0">
                Receita recorrente de {{ $assinaturasAtivas }} assinatura(s):
                R$ {{ number_format($receitaMensal, 2, ',', '.') }}.
            </p>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="busca" class="form-label small text-muted">Clínica</label>
                    <input id="busca" type="search" class="form-control" name="busca" value="{{ $busca }}" placeholder="Nome da clínica">
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
                        <a href="{{ route('painel.assinaturas.index') }}" class="btn btn-outline-secondary">Limpar</a>
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
                            <th>Plano</th>
                            <th>Estado</th>
                            <th>Período</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assinaturas as $assinatura)
                            <tr>
                                <td>
                                    <a href="{{ route('painel.clinicas.show', $assinatura->clinic_id) }}" class="text-decoration-none">
                                        {{ $assinatura->clinic?->name ?? '—' }}
                                    </a>
                                </td>
                                <td>{{ $assinatura->plan?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $assinatura->isActive() ? 'success' : 'secondary' }}">
                                        {{ $assinatura->statusLabel() }}
                                    </span>
                                    @if ($assinatura->isOnGracePeriod())
                                        <span class="badge bg-warning text-dark">carência</span>
                                    @endif
                                </td>
                                <td class="text-muted small">
                                    {{ $assinatura->current_period_end?->format('d/m/Y') ?? '—' }}
                                    @if ($assinatura->isExpired() && $assinatura->isActive())
                                        <span class="badge bg-danger">vencida</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if (auth()->user()->isSuperAdmin())
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <form method="POST" action="{{ route('painel.assinaturas.renovar', $assinatura) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">Renovar um mês</button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                @foreach ($estados as $chave => $rotulo)
                                                    @continue($chave === $assinatura->status)
                                                    <li>
                                                        <form method="POST" action="{{ route('painel.assinaturas.status', $assinatura) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="{{ $chave }}">
                                                            <button type="submit" class="dropdown-item">Marcar como {{ $rotulo }}</button>
                                                        </form>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Nenhuma assinatura encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $assinaturas->links() }}
            </div>
        </div>
    </div>
@endsection
