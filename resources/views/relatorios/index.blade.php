@extends('layouts.app')

@section('title', 'Relatórios')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">Relatórios</h1>
            <div class="text-muted small">Visão geral de financeiro, agenda e estoque no período selecionado.</div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('relatorios.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="de" class="form-label">De</label>
                    <input type="date" id="de" name="de" class="form-control" value="{{ $de->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label for="ate" class="form-label">Até</label>
                    <input type="date" id="ate" name="ate" class="form-control" value="{{ $ate->format('Y-m-d') }}">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="relatoriosTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aba-financeiro" type="button" role="tab"><i class="bi bi-cash-stack me-1"></i>Financeiro</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-agenda" type="button" role="tab"><i class="bi bi-calendar-check me-1"></i>Agenda</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-estoque" type="button" role="tab"><i class="bi bi-box-seam me-1"></i>Estoque</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="aba-financeiro" role="tabpanel">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Total a receber</div>
                            <div class="fs-4 fw-bold">R$ {{ number_format($resumoFinanceiro['a_receber'], 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Em aberto</div>
                            <div class="fs-4 fw-bold">R$ {{ number_format($resumoFinanceiro['em_aberto'], 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Já pago</div>
                            <div class="fs-4 fw-bold">R$ {{ number_format($resumoFinanceiro['pago'], 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Recebido no período</div>
                            <div class="fs-4 fw-bold text-success">R$ {{ number_format($resumoFinanceiro['recebido_no_periodo'], 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Contas a receber no período</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vencimento</th>
                                <th>Paciente</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">Pago</th>
                                <th class="text-end">Saldo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contas as $conta)
                                <tr>
                                    <td>{{ $conta->vencimento->format('d/m/Y') }}</td>
                                    <td>{{ $conta->paciente?->nome ?? '—' }}</td>
                                    <td class="text-end">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                                    <td class="text-end">R$ {{ number_format($conta->valor_pago, 2, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">R$ {{ number_format($conta->saldo, 2, ',', '.') }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $conta->status === 'pago' ? 'success' : ($conta->status === 'parcial' ? 'primary' : 'warning') }}">
                                            {{ ucfirst($conta->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma conta no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="aba-agenda" role="tabpanel">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Consultas no período</div>
                            <div class="fs-4 fw-bold">{{ $resumoAgenda['total'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Atendidas</div>
                            <div class="fs-4 fw-bold text-success">{{ $resumoAgenda['atendidas'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Canceladas</div>
                            <div class="fs-4 fw-bold text-danger">{{ $resumoAgenda['canceladas'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Faltas</div>
                            <div class="fs-4 fw-bold text-warning">{{ $resumoAgenda['faltas'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Consultas no período</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Início</th>
                                <th>Paciente</th>
                                <th>Profissional</th>
                                <th>Status</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($consultas as $consulta)
                                <tr>
                                    <td>{{ $consulta->inicio->format('d/m/Y H:i') }}</td>
                                    <td>{{ $consulta->paciente?->nome ?? '—' }}</td>
                                    <td>{{ $consulta->profissional?->nome ?? '—' }}</td>
                                    <td>
                                        <span class="badge" style="background-color: {{ $consulta->status?->cor ?? '#6c757d' }}">
                                            {{ $consulta->status?->label ?? '-' }}
                                        </span>
                                    </td>
                                    <td>{{ $consulta->observacao ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma consulta no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="aba-estoque" role="tabpanel">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Produtos cadastrados</div>
                            <div class="fs-4 fw-bold">{{ $resumoEstoque['produtos'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Com estoque baixo</div>
                            <div class="fs-4 fw-bold text-warning">{{ $resumoEstoque['baixo'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Entradas no período</div>
                            <div class="fs-4 fw-bold text-success">{{ number_format($resumoEstoque['entradas'], 3, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="text-muted small">Saídas no período</div>
                            <div class="fs-4 fw-bold text-danger">{{ number_format($resumoEstoque['saidas'], 3, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Produtos com estoque baixo</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Produto</th>
                                <th class="text-end">Estoque</th>
                                <th class="text-end">Mínimo</th>
                                <th>Unidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($produtosBaixo as $produto)
                                <tr class="table-warning">
                                    <td>{{ $produto->nome }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($produto->estoque, 3, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($produto->estoque_minimo, 3, ',', '.') }}</td>
                                    <td>{{ $produto->unidade }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Nenhum produto com estoque baixo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Últimas movimentações (máx. 100)</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Produto</th>
                                <th>Tipo</th>
                                <th class="text-end">Qtde</th>
                                <th>Usuário</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($movimentacoes as $movimento)
                                <tr>
                                    <td>{{ $movimento->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $movimento->product?->nome ?? '—' }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $movimento->tipo === 'entrada' ? 'success' : 'danger' }}">
                                            {{ ucfirst($movimento->tipo) }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ number_format($movimento->quantidade, 3, ',', '.') }}</td>
                                    <td>{{ $movimento->user?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma movimentação no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection