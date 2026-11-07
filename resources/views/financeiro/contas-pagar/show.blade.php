@extends('layouts.app')

@section('title', 'Conta a pagar')

@section('content')
    @php
        $status = $conta->statusEfetivo();
        $classes = ['pendente' => 'warning', 'atrasado' => 'danger', 'pago' => 'success', 'cancelado' => 'secondary'];
    @endphp

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('financeiro.contas-pagar.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">{{ $conta->nome_conta }}</h1>
                <p class="text-muted small mb-0">Vencimento {{ $conta->data_vencimento?->format('d/m/Y') ?? '—' }}</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('financeiro.update')
                @if ($status !== 'cancelado')
                    <a href="{{ route('financeiro.contas-pagar.edit', $conta) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>
                @endif
            @endcan
            @can('financeiro.delete')
                @if ($status !== 'cancelado')
                    <form method="POST" action="{{ route('financeiro.contas-pagar.cancelar', $conta) }}" onsubmit="return confirm('Cancelar esta conta?');">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Cancelar conta</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @include('financeiro.partials.nav')

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>Dados da conta</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Status</dt>
                        <dd class="col-7"><span class="badge text-bg-{{ $classes[$status] ?? 'secondary' }}">{{ ucfirst($status) }}</span></dd>
                        <dt class="col-5">Fornecedor</dt>
                        <dd class="col-7">{{ $conta->supplier?->nome ?? '—' }}</dd>
                        <dt class="col-5">Categoria</dt>
                        <dd class="col-7">{{ $conta->categoria?->nome ?? '—' }}</dd>
                        <dt class="col-5">Tipo</dt>
                        <dd class="col-7">{{ ucfirst($conta->tipo) }} / {{ strtoupper($conta->tipo_documento) }}</dd>
                        <dt class="col-5">Valor</dt>
                        <dd class="col-7 fw-bold">R$ {{ number_format((float) $conta->valor, 2, ',', '.') }}</dd>
                        <dt class="col-5">Pago</dt>
                        <dd class="col-7">R$ {{ number_format($conta->valor_pago, 2, ',', '.') }}</dd>
                        <dt class="col-5">Saldo</dt>
                        <dd class="col-7 fw-bold">R$ {{ number_format($conta->saldo, 2, ',', '.') }}</dd>
                        <dt class="col-5">Descrição</dt>
                        <dd class="col-7">{{ $conta->descricao ?: '—' }}</dd>
                        <dt class="col-5">Observações</dt>
                        <dd class="col-7">{{ $conta->observacoes ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @can('financeiro.receive')
                @if ($status !== 'cancelado' && $conta->saldo > 0)
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Registrar baixa</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('financeiro.contas-pagar.baixar', $conta) }}" class="row g-2 align-items-end">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <div class="col-md-3">
                                    <label for="valor" class="form-label">Valor *</label>
                                    <input type="number" step="0.01" min="0.01" max="{{ $conta->saldo }}" id="valor" name="valor" value="{{ old('valor', number_format($conta->saldo, 2, '.', '')) }}" class="form-control @error('valor') is-invalid @enderror" required>
                                    @error('valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="payment_method_id" class="form-label">Forma</label>
                                    <select id="payment_method_id" name="payment_method_id" class="form-select">
                                        <option value="">Não informada</option>
                                        @foreach ($formasPagamento as $forma)
                                            <option value="{{ $forma->id }}" @selected(old('payment_method_id') == $forma->id)>{{ $forma->label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="data" class="form-label">Data *</label>
                                    <input type="date" id="data" name="data" value="{{ old('data', now()->format('Y-m-d')) }}" class="form-control" required>
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button class="btn btn-danger"><i class="bi bi-check-lg me-1"></i>Baixar</button>
                                </div>
                                <div class="col-12">
                                    <input type="text" name="observacoes" class="form-control" value="{{ old('observacoes') }}" placeholder="Observações da baixa (opcional)">
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-2"></i>Baixas registradas</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Data</th><th>Forma</th><th>Responsável</th><th class="text-end">Valor</th></tr></thead>
                        <tbody>
                            @forelse ($conta->pagamentos as $baixa)
                                <tr>
                                    <td>{{ $baixa->data?->format('d/m/Y') }}</td>
                                    <td>{{ $baixa->paymentMethod?->label ?? '—' }}</td>
                                    <td>{{ $baixa->user?->name ?? '—' }}</td>
                                    <td class="text-end fw-semibold">R$ {{ number_format((float) $baixa->valor, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">Nenhuma baixa registrada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
