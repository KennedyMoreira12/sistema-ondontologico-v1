@extends('layouts.app')

@section('title', 'Convênios')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Convênios</h1>
            <p class="text-muted small mb-0">{{ $operadoras->total() }} operadora(s) encontrada(s)</p>
        </div>
        @can('convenios.create')
            <a href="{{ route('convenios.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nova operadora
            </a>
        @endcan
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('convenios.index') }}" class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="busca" value="{{ request('busca') }}" class="form-control" placeholder="Buscar por nome ou CNPJ">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Todos os status</option>
                        <option value="ativo" @selected(request('status') === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(request('status') === 'inativo')>Inativo</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>CNPJ</th>
                            <th>Telefone</th>
                            <th>Planos</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($operadoras as $operadora)
                            <tr>
                                <td>{{ $operadora->id }}</td>
                                <td>{{ $operadora->nome }}</td>
                                <td>{{ $operadora->cnpj ? \Illuminate\Support\Str::mask($operadora->cnpj, '*', 3, 7) : '—' }}</td>
                                <td>{{ $operadora->phone ?? '—' }}</td>
                                <td>{{ $operadora->plans_count }}</td>
                                <td>
                                    @if ($operadora->is_active)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('convenios.update')
                                        <a href="{{ route('convenios.edit', $operadora) }}" class="btn btn-sm btn-outline-secondary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('convenios.delete')
                                        <form method="POST" action="{{ route('convenios.destroy', $operadora) }}" class="d-inline" onsubmit="return confirm('Excluir esta operadora e seus planos?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nenhuma operadora encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $operadoras->links() }}
            </div>
        </div>
    </div>
@endsection