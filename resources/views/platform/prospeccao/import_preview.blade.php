@extends('layouts.platform')

@section('title', 'Prévia da importação')
@section('page-title', 'Prévia da importação')

@php
    $cores = [
        'novo' => 'success',
        'possivel' => 'warning text-dark',
        'duplicado' => 'info text-dark',
        'existente' => 'secondary',
        'atualizado' => 'secondary',
        'rejeitado' => 'danger',
    ];
    $rotulos = [
        'novo' => 'Novo',
        'possivel' => 'Possível duplicado',
        'duplicado' => 'Duplicado (fundir)',
        'existente' => 'Já cadastrado',
        'atualizado' => 'Atualizado',
        'rejeitado' => 'Rejeitado',
    ];
@endphp

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">Prévia da importação</h1>
            <p class="text-muted small mb-0">
                Arquivo <strong>{{ $arquivo }}</strong>. Confira abaixo — os contatos só entram na base quando você confirmar.
            </p>
        </div>
        <a href="{{ route('painel.prospeccao.importar') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Trocar arquivo
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Serão importados</div>
                <div class="h4 mb-0 text-success">{{ $preview['imported'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Possíveis duplicados</div>
                <div class="h4 mb-0 text-warning">{{ $preview['possiveis'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Duplicados (fundir)</div>
                <div class="h4 mb-0 text-info">{{ $preview['duplicated'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Já cadastrados</div>
                <div class="h4 mb-0 text-secondary">{{ $preview['existentes'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Com pendência</div>
                <div class="h4 mb-0">{{ $preview['pending'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Rejeitados</div>
                <div class="h4 mb-0 text-danger">{{ $preview['rejected'] }}</div>
            </div></div>
        </div>
    </div>

    @if ($preview['possiveis'] > 0)
        <div class="alert alert-warning py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Os possíveis duplicados serão importados e ficam marcados para revisão — nada é fundido automaticamente sem
            telefone e nome iguais.
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Linhas do arquivo ({{ $preview['total'] }})</h2>
            <div class="table-responsive" style="max-height: 460px; overflow-y: auto;">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Clínica / profissional</th>
                            <th>Cidade</th>
                            <th>Telefone</th>
                            <th>Situação</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($preview['linhas'] as $linha)
                            <tr>
                                <td class="small">{{ $linha['name'] !== '' ? $linha['name'] : '—' }}</td>
                                <td class="small text-muted">{{ $linha['city'] !== '' ? $linha['city'] : '—' }}</td>
                                <td class="small text-muted">{{ $linha['phone'] !== '' ? $linha['phone'] : '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $cores[$linha['situacao']] ?? 'secondary' }}">
                                        {{ $rotulos[$linha['situacao']] ?? $linha['situacao'] }}
                                    </span>
                                </td>
                                <td class="small text-muted">{{ $linha['motivo'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted small">Nenhuma linha para mostrar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('painel.prospeccao.importar.confirmar') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2-circle me-1"></i>Confirmar importação
            </button>
        </form>

        <form method="POST" action="{{ route('painel.prospeccao.importar.cancelar') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Cancelar</button>
        </form>
    </div>
@endsection
