@extends('layouts.app')

@section('title', 'Estoque')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Estoque</h1>
        @can('estoque.create')
            <a href="{{ route('estoque.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo produto</a>
        @endcan
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('estoque.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="busca" class="form-label">Buscar</label>
                    <input type="search" id="busca" name="busca" class="form-control" value="{{ request('busca') }}" placeholder="Nome ou código...">
                </div>
                <div class="col-md-3">
                    <label for="categoria" class="form-label">Categoria</label>
                    <select id="categoria" name="categoria" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}" @selected(request('categoria') == $categoria->id)>{{ $categoria->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="baixo" name="baixo" value="1" @checked(request('baixo'))>
                        <label class="form-check-label" for="baixo">Só estoque baixo</label>
                    </div>
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
                        <th>Produto</th>
                        <th>Código</th>
                        <th>Categoria</th>
                        <th class="text-end">Estoque</th>
                        <th class="text-end">Mínimo</th>
                        <th class="text-end">Preço</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($produtos as $produto)
                        <tr @class(['table-warning' => $produto->estoque_baixo])>
                            <td>{{ $produto->nome }}</td>
                            <td>{{ $produto->codigo ?? '—' }}</td>
                            <td>{{ $produto->categoria?->name ?? '—' }}</td>
                            <td class="text-end fw-semibold">
                                {{ number_format($produto->estoque, 3, ',', '.') }} <small class="text-muted">{{ $produto->unidade }}</small>
                                @if ($produto->estoque_baixo)
                                    <span class="badge text-bg-warning ms-1">Estoque baixo</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($produto->estoque_minimo, 3, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($produto->preco, 2, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $produto->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $produto->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('estoque.update')
                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#movimento-{{ $produto->id }}" aria-expanded="false" title="Movimentar estoque">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <a href="{{ route('estoque.edit', $produto) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('estoque.delete')
                                    <form method="POST" action="{{ route('estoque.destroy', $produto) }}" class="d-inline" onsubmit="return confirm('Excluir o produto {{ $produto->nome }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                        @can('estoque.update')
                            <tr class="collapse" id="movimento-{{ $produto->id }}">
                                <td colspan="8" class="bg-light">
                                    <form method="POST" action="{{ route('estoque.movimento', $produto) }}" class="row g-2 align-items-end">
                                        @csrf
                                        <div class="col-md-2">
                                            <label class="form-label">Tipo *</label>
                                            <select name="tipo" class="form-select" required>
                                                <option value="entrada">Entrada</option>
                                                <option value="saida">Saída</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Quantidade *</label>
                                            <input type="number" step="0.001" min="0.001" name="quantidade" class="form-control" value="{{ old('quantidade') }}" required>
                                            @error('quantidade')<div class="text-danger small">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Motivo</label>
                                            <input type="text" name="motivo" class="form-control" value="{{ old('motivo') }}">
                                        </div>
                                        <div class="col-md-2 d-grid">
                                            <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Registrar</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endcan
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum produto encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $produtos->links() }}
        </div>
    </div>
@endsection