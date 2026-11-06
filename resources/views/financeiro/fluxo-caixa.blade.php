@extends('layouts.app')

@section('title', 'Financeiro — Fluxo de caixa')

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Fluxo de caixa</h1>
        <p class="text-muted small mb-0">Entradas recebidas menos saídas pagas no período. Contas em aberto aparecem separadas.</p>
    </div>

    @include('financeiro.partials.nav')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('financeiro.fluxo-caixa') }}" class="row g-2 align-items-end">
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
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
                <div class="card-body">
                    <div class="text-muted small">Entradas recebidas</div>
                    <div class="fs-5 fw-bold text-success">R$ {{ number_format($totalRecebido, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-danger h-100">
                <div class="card-body">
                    <div class="text-muted small">Saídas pagas</div>
                    <div class="fs-5 fw-bold text-danger">R$ {{ number_format($totalPago, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body">
                    <div class="text-muted small">Saldo do período</div>
                    <div class="fs-5 fw-bold {{ $saldo < 0 ? 'text-danger' : 'text-primary' }}">R$ {{ number_format($saldo, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Em aberto (não é caixa)</div>
                    <div class="small">A receber: R$ {{ number_format($devedores, 2, ',', '.') }}</div>
                    <div class="small">A pagar: R$ {{ number_format($aPagar, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Entradas por forma de pagamento</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Forma</th><th class="text-end">Lançamentos</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($porForma as $linha)
                                <tr>
                                    <td>{{ $linha['forma'] }}</td>
                                    <td class="text-end">{{ $linha['quantidade'] }}</td>
                                    <td class="text-end fw-semibold">R$ {{ number_format($linha['total'], 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Nenhuma entrada no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Saídas por conta</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Conta</th><th>Data</th><th class="text-end">Valor</th></tr></thead>
                        <tbody>
                            @forelse ($pagamentos->take(15) as $baixa)
                                <tr>
                                    <td>{{ $baixa->conta?->nome_conta ?? '—' }}</td>
                                    <td>{{ $baixa->data?->format('d/m/Y') }}</td>
                                    <td class="text-end">R$ {{ number_format((float) $baixa->valor, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Nenhuma saída no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">Últimas entradas</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Data</th><th>Paciente</th><th>Serviço</th><th>Forma</th><th class="text-end">Valor</th></tr></thead>
                <tbody>
                    @forelse ($recebimentos->take(20) as $pagamento)
                        <tr>
                            <td>{{ $pagamento->data?->format('d/m/Y') }}</td>
                            <td>{{ $pagamento->paciente?->nome ?? 'Não vinculado' }}</td>
                            <td>{{ $pagamento->servico ?: '—' }}</td>
                            <td>{{ $pagamento->paymentMethod?->label ?? '—' }}</td>
                            <td class="text-end">R$ {{ number_format((float) $pagamento->valor, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Nenhum recebimento no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
