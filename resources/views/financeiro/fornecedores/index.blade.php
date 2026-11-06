@extends('layouts.app')

@section('title', 'Financeiro — Fornecedores')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Fornecedores</h1>
            <p class="text-muted small mb-0">Quem recebe as contas a pagar da clínica.</p>
        </div>
        @can('financeiro.create')
            <a href="{{ route('financeiro.fornecedores.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Novo fornecedor</a>
        @endcan
    </div>

    @include('financeiro.partials.nav')

    @if ($errors->any())
        <div class="alert alert-danger">@foreach ($errors->all() as $erro)<div>{{ $erro }}</div>@endforeach</div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('financeiro.fornecedores.index') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="busca" class="form-label">Buscar</label>
                    <input type="search" id="busca" name="busca" value="{{ request('busca') }}" class="form-control" placeholder="Nome, documento ou contato...">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
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
                        <th>Documento</th>
                        <th>Contato</th>
                        <th class="text-end">Contas em aberto</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fornecedores as $fornecedor)
                        <tr>
                            <td class="fw-semibold">{{ $fornecedor->nome }}</td>
                            <td>{{ $fornecedor->documento ?: '—' }}</td>
                            <td>
                                {{ $fornecedor->contato ?: '—' }}
                                @if ($fornecedor->phone)
                                    <div class="text-muted small">{{ $fornecedor->phone }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ $fornecedor->contas_abertas_count }}</td>
                            <td><span class="badge text-bg-{{ $fornecedor->is_active ? 'success' : 'secondary' }}">{{ $fornecedor->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                            <td class="text-end">
                                @can('financeiro.update')
                                    <a href="{{ route('financeiro.fornecedores.edit', $fornecedor) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('financeiro.delete')
                                    <form method="POST" action="{{ route('financeiro.fornecedores.destroy', $fornecedor) }}" class="d-inline" onsubmit="return confirm('Excluir este fornecedor?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum fornecedor encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $fornecedores->links() }}</div>
    </div>
@endsection
