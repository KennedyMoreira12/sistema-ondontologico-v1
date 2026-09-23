@extends('layouts.app')

@section('title', 'Auditoria')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Trilha de auditoria</h1>
            <p class="text-muted small mb-0">Registro das ações realizadas no sistema.</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.auditoria.index') }}" class="row g-2 mb-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="acao" value="{{ request('acao') }}" class="form-control" placeholder="Buscar por ação (ex.: criar, atualizar)">
                    </div>
                </div>
                <div class="col-md-4">
                    <input type="text" name="tabela" value="{{ request('tabela') }}" class="form-control" placeholder="Filtrar por tabela (ex.: pacientes)">
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data/hora</th>
                            <th>Usuário</th>
                            <th>Ação</th>
                            <th>Tabela</th>
                            <th>Registro</th>
                            <th>IP</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="text-nowrap">{{ $log->created_at->translatedFormat('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->user?->name ?? '—' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $log->acao }}</span></td>
                                <td><code>{{ $log->tabela }}</code></td>
                                <td>{{ $log->registro_id }}</td>
                                <td class="text-muted">{{ $log->ip }}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#log-{{ $log->id }}">
                                        Detalhes
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="log-{{ $log->id }}">
                                <td colspan="7" class="bg-light">
                                    <div class="row g-3">
                                        @if ($log->antes)
                                            <div class="col-md-6">
                                                <strong class="small">Antes</strong>
                                                <pre class="bg-white border p-2 rounded small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->antes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                        @if ($log->depois)
                                            <div class="col-md-6">
                                                <strong class="small">Depois</strong>
                                                <pre class="bg-white border p-2 rounded small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->depois, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nenhum registro de auditoria encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection