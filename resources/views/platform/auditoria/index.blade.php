@extends('layouts.platform')

@section('title', 'Auditoria')
@section('page-title', 'Auditoria da plataforma')

@section('content')
    <div class="mb-4">
        <h1 class="h4 mb-0">Auditoria</h1>
        <p class="text-muted small mb-0">
            Ações feitas pelo painel da plataforma. O que acontece dentro de uma clínica fica no
            <code>audit_logs</code> do banco dela.
        </p>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="acao" class="form-label small text-muted">Ação</label>
                    <select id="acao" class="form-select" name="acao">
                        <option value="">Todas</option>
                        @foreach ($acoes as $registrada)
                            <option value="{{ $registrada }}" @selected(request('acao') === $registrada)>{{ $registrada }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="clinica" class="form-label small text-muted">Clínica</label>
                    <select id="clinica" class="form-select" name="clinica">
                        <option value="">Todas</option>
                        @foreach ($clinicas as $clinica)
                            <option value="{{ $clinica->id }}" @selected((int) request('clinica') === $clinica->id)>{{ $clinica->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="admin" class="form-label small text-muted">Responsável</label>
                    <select id="admin" class="form-select" name="admin">
                        <option value="">Todos</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected((int) request('admin') === $admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filtrar</button>
                    <a href="{{ route('painel.auditoria.index') }}" class="btn btn-outline-secondary">Limpar</a>
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
                            <th>Quando</th>
                            <th>Ação</th>
                            <th>Clínica</th>
                            <th>Responsável</th>
                            <th>IP</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registros as $registro)
                            <tr>
                                <td class="text-nowrap text-muted small">{{ $registro->created_at?->format('d/m/Y H:i') }}</td>
                                <td><code>{{ $registro->action }}</code></td>
                                <td class="text-muted small">{{ $registro->clinic?->name ?? '—' }}</td>
                                <td class="text-muted small">{{ $registro->adminUser?->name ?? 'sistema' }}</td>
                                <td class="text-muted small font-monospace">{{ $registro->ip ?? '—' }}</td>
                                <td class="small text-muted">{{ $registro->metadataSummary() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Nenhuma ação registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $registros->links() }}
            </div>
        </div>
    </div>
@endsection
