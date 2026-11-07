@extends('layouts.app')

@section('title', 'Financeiro — Contas a pagar')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Contas a pagar</h1>
            <p class="text-muted small mb-0">Compromissos da clínica, com vencimento e baixa registrada.</p>
        </div>
        @can('financeiro.create')
            <a href="{{ route('financeiro.contas-pagar.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nova conta</a>
        @endcan
    </div>

    @include('financeiro.partials.nav')

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-warning h-100">
                <div class="card-body">
                    <div class="text-muted small">Em aberto</div>
                    <div class="fs-5 fw-bold">R$ {{ number_format($resumo['emAberto'], 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-danger h-100">
                <div class="card-body">
                    <div class="text-muted small">Atrasadas</div>
                    <div class="fs-5 fw-bold text-danger">R$ {{ number_format($resumo['atrasado'], 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body">
                    <div class="text-muted small">Vencem em 7 dias</div>
                    <div class="fs-5 fw-bold">R$ {{ number_format($resumo['vencendo'], 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
                <div class="card-body">
                    <div class="text-muted small">Pagas</div>
                    <div class="fs-5 fw-bold text-success">R$ {{ number_format($resumo['pago'], 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('financeiro.contas-pagar.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="busca" class="form-label">Buscar</label>
                    <input type="search" id="busca" name="busca" class="form-control" value="{{ request('busca') }}" placeholder="Conta, descrição ou fornecedor...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendente" @selected(request('status') === 'pendente')>Pendente</option>
                        <option value="atrasado" @selected(request('status') === 'atrasado')>Atrasado</option>
                        <option value="pago" @selected(request('status') === 'pago')>Pago</option>
                        <option value="cancelado" @selected(request('status') === 'cancelado')>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="supplier_id" class="form-label">Fornecedor</label>
                    <select id="supplier_id" name="supplier_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($fornecedores as $fornecedor)
                            <option value="{{ $fornecedor->id }}" @selected(request('supplier_id') == $fornecedor->id)>{{ $fornecedor->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-primary"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Conta</th>
                        <th>Fornecedor</th>
                        <th>Vencimento</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Pago</th>
                        <th class="text-end">Saldo</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contas as $conta)
                        @php
                            $status = $conta->statusEfetivo();
                            $classes = ['pendente' => 'warning', 'atrasado' => 'danger', 'pago' => 'success', 'cancelado' => 'secondary'];
                        @endphp
                        <tr @class(['table-success' => $status === 'pago', 'table-danger' => $status === 'atrasado'])>
                            <td>
                                <div class="fw-semibold">{{ $conta->nome_conta }}</div>
                                <div class="text-muted small">{{ $conta->categoria?->nome ?? ucfirst($conta->tipo) }}</div>
                            </td>
                            <td>{{ $conta->supplier?->nome ?? '—' }}</td>
                            <td>{{ $conta->data_vencimento?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-end">R$ {{ number_format((float) $conta->valor, 2, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($conta->valor_pago, 2, ',', '.') }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($conta->saldo, 2, ',', '.') }}</td>
                            <td><span class="badge text-bg-{{ $classes[$status] ?? 'secondary' }}">{{ ucfirst($status) }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('financeiro.contas-pagar.show', $conta) }}" class="btn btn-sm btn-outline-primary" title="Ver conta"><i class="bi bi-eye"></i></a>
                                @can('financeiro.update')
                                    @if ($status !== 'cancelado')
                                        <a href="{{ route('financeiro.contas-pagar.edit', $conta) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma conta encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $contas->links() }}</div>
    </div>
@endsection
