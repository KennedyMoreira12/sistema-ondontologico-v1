@extends('layouts.app')

@section('title', 'Profissionais')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Profissionais</h1>
        @can('profissionais.create')
            <a href="{{ route('profissionais.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo profissional</a>
        @endcan
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('profissionais.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="busca" class="form-label">Buscar</label>
                    <input type="search" id="busca" name="busca" class="form-control" value="{{ request('busca') }}" placeholder="{{ vertical_permite('odontograma') ? 'Nome, CRO ou registro profissional...' : 'Nome ou registro profissional...' }}">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="ativo" @selected(request('status') === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(request('status') === 'inativo')>Inativo</option>
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
                        <th>Nome</th>
                        <th>Registro no conselho</th>
                        <th>Tipo</th>
                        <th>Especialidades</th>
                        <th>Comissão</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profissionais as $profissional)
                        <tr>
                            <td>{{ $profissional->nome }}</td>
                            <td>{{ $profissional->registroConselho() ?? '—' }}</td>
                            <td>{{ ucfirst($profissional->tipo) }}</td>
                            <td>
                                @forelse ($profissional->specialties as $especialidade)
                                    <span class="badge text-bg-light border">{{ $especialidade->name }}</span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>{{ $profissional->comissao ? number_format($profissional->comissao, 1, ',', '.').'%' : '—' }}</td>
                            <td>
                                <span class="badge {{ $profissional->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $profissional->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('profissionais.update')
                                    <a href="{{ route('profissionais.edit', $profissional) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('profissionais.delete')
                                    <form method="POST" action="{{ route('profissionais.destroy', $profissional) }}" class="d-inline" onsubmit="return confirm('Excluir o profissional {{ $profissional->nome }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum profissional encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $profissionais->links() }}
        </div>
    </div>
@endsection