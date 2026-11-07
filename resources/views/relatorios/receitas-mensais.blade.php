@extends('layouts.app')

@section('title', 'Relatórios — Receitas')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Receitas do período</h1>
            <p class="text-muted small mb-0">Total recebido, por serviço, por forma de pagamento e por mês.</p>
        </div>
    </div>

    @include('relatorios.partials.nav')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('relatorios.receitas') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="de" class="form-label">De</label>
                    <input type="date" id="de" name="de" class="form-control" value="{{ $de->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label for="ate" class="form-label">Até</label>
                    <input type="date" id="ate" name="ate" class="form-control" value="{{ $ate->format('Y-m-d') }}">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
                <div class="col-md-4 d-grid">
                    <a href="{{ route('relatorios.exportar', ['tipo' => 'receitas', 'de' => $de->format('Y-m-d'), 'ate' => $ate->format('Y-m-d')]) }}" class="btn btn-outline-success">
                        <i class="bi bi-filetype-csv me-1"></i>Exportar CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-success h-100"><div class="card-body">
                <div class="text-muted small">Recebido</div>
                <div class="fs-5 fw-bold text-success">R$ {{ number_format($totalRecebido, 2, ',', '.') }}</div>
                <div class="text-muted small">{{ number_format($pagamentos->count(), 0, ',', '.') }} lançamentos</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-danger h-100"><div class="card-body">
                <div class="text-muted small">Saídas pagas</div>
                <div class="fs-5 fw-bold text-danger">R$ {{ number_format($totalPago, 2, ',', '.') }}</div>
                <div class="text-muted small">Baixas de contas a pagar</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-primary h-100"><div class="card-body">
                <div class="text-muted small">Saldo do período</div>
                <div class="fs-5 fw-bold {{ $saldo < 0 ? 'text-danger' : 'text-primary' }}">R$ {{ number_format($saldo, 2, ',', '.') }}</div>
                <div class="text-muted small">Recebido menos pago</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-warning h-100"><div class="card-body">
                <div class="text-muted small">Sem paciente vinculado</div>
                <div class="fs-5 fw-bold text-warning">{{ number_format($semPacienteQuantidade, 0, ',', '.') }}</div>
                <div class="text-muted small">R$ {{ number_format($semPacienteValor, 2, ',', '.') }} no caixa</div>
            </div></div>
        </div>
    </div>

    <div class="alert alert-info d-flex gap-2 py-2">
        <i class="bi bi-info-circle"></i>
        <div class="small">
            Lançamentos antigos sem paciente continuam somando no caixa e aparecem aqui. O vínculo é revisado em
            <a href="{{ route('financeiro.pagamentos.index') }}">Financeiro → Pagamentos</a>; nenhum cadastro é unido automaticamente.
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Por serviço</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Serviço</th><th class="text-end">Qtd.</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($porServico as $linha)
                                <tr>
                                    <td>{{ $linha['servico'] }}</td>
                                    <td class="text-end">{{ $linha['quantidade'] }}</td>
                                    <td class="text-end fw-semibold">R$ {{ number_format($linha['total'], 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Nenhuma receita no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Por forma de pagamento</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Forma</th><th class="text-end">Qtd.</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($porForma as $linha)
                                <tr>
                                    <td>{{ $linha['forma'] }}</td>
                                    <td class="text-end">{{ $linha['quantidade'] }}</td>
                                    <td class="text-end fw-semibold">R$ {{ number_format($linha['total'], 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Nenhuma receita no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Por mês</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Mês</th><th class="text-end">Qtd.</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($porMes as $linha)
                                <tr>
                                    <td>{{ $linha['mes'] }}</td>
                                    <td class="text-end">{{ $linha['quantidade'] }}</td>
                                    <td class="text-end fw-semibold">R$ {{ number_format($linha['total'], 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Nenhuma receita no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Lançamentos do período</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Paciente</th>
                        <th>Serviço</th>
                        <th>Forma</th>
                        <th>Observações</th>
                        <th class="text-end">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pagamentos as $pagamento)
                        <tr>
                            <td>{{ $pagamento->data?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                @if ($pagamento->paciente)
                                    {{ $pagamento->paciente->nome }}
                                @else
                                    <span class="text-muted fst-italic">não vinculado</span>
                                @endif
                            </td>
                            <td>{{ $pagamento->servico ?: '—' }}</td>
                            <td>{{ $pagamento->paymentMethod?->label ?? '—' }}</td>
                            <td class="text-muted small">{{ \Illuminate\Support\Str::limit($pagamento->observacoes, 70) }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format((float) $pagamento->valor, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum lançamento no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
