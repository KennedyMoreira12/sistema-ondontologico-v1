@extends('layouts.app')

@section('title', 'Contratos')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Contratos</h1>
        @can('contratos.create')
            <a href="{{ route('contratos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo contrato</a>
        @endcan
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('contratos.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="busca" class="form-label">Paciente</label>
                    <input type="search" id="busca" name="busca" class="form-control" value="{{ request('busca') }}" placeholder="Nome do paciente...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Paciente</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Assinado em</th>
                        <th>Registrado por</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contratos as $contrato)
                        <tr>
                            <td>
                                <a href="{{ route('pacientes.show', $contrato->paciente) }}">{{ $contrato->paciente->nome }}</a>
                            </td>
                            <td>{{ $contrato->tipo }}</td>
                            <td>
                                @php
                                    $badges = ['pendente' => 'text-bg-warning', 'assinado' => 'text-bg-success', 'cancelado' => 'text-bg-secondary'];
                                @endphp
                                <span class="badge {{ $badges[$contrato->status] ?? 'text-bg-secondary' }}">{{ ucfirst($contrato->status) }}</span>
                            </td>
                            <td>{{ $contrato->assinado_em?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $contrato->user?->name ?? '—' }}</td>
                            <td class="text-end">
                                @can('contratos.update')
                                    <a href="{{ route('contratos.edit', $contrato) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('contratos.delete')
                                    <form method="POST" action="{{ route('contratos.destroy', $contrato) }}" class="d-inline" onsubmit="return confirm('Excluir o contrato de {{ $contrato->paciente->nome }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum contrato encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $contratos->links() }}
        </div>
    </div>
@endsection