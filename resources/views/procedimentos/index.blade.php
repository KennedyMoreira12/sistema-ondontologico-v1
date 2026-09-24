@extends('layouts.app')

@section('title', 'Procedimentos')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Procedimentos</h1>
        @can('procedimentos.create')
            <a href="{{ route('procedimentos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo procedimento</a>
        @endcan
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('procedimentos.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="busca" class="form-label">Buscar</label>
                    <input type="search" id="busca" name="busca" class="form-control" value="{{ request('busca') }}" placeholder="Nome ou código...">
                </div>
                <div class="col-md-3">
                    <label for="categoria" class="form-label">Categoria</label>
                    <select id="categoria" name="categoria" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria }}" @selected(request('categoria') === $categoria)>{{ $categoria }}</option>
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
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th class="text-center">Duração</th>
                        <th class="text-end">Valor padrão</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($procedimentos as $procedimento)
                        <tr>
                            <td>{{ $procedimento->codigo ?? '—' }}</td>
                            <td>{{ $procedimento->nome }}</td>
                            <td>{{ $procedimento->categoria ?? '—' }}</td>
                            <td class="text-center">{{ $procedimento->duracao_minutos ? $procedimento->duracao_minutos.' min' : '—' }}</td>
                            <td class="text-end">R$ {{ number_format($procedimento->valor_padrao, 2, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $procedimento->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $procedimento->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('procedimentos.update')
                                    <a href="{{ route('procedimentos.edit', $procedimento) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('procedimentos.delete')
                                    <form method="POST" action="{{ route('procedimentos.destroy', $procedimento) }}" class="d-inline" onsubmit="return confirm('Excluir o procedimento {{ $procedimento->nome }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum procedimento encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $procedimentos->links() }}
        </div>
    </div>
@endsection