@extends('layouts.app')

@section('title', 'Financeiro — Contas a receber')

@section('content')
    @include('financeiro.partials.resumo')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('financeiro.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="busca" class="form-label">Buscar paciente</label>
                    <input type="search" id="busca" name="busca" class="form-control" value="{{ request('busca') }}" placeholder="Nome do paciente...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="aberta" @selected(request('status') === 'aberta')>Em aberto</option>
                        <option value="parcial" @selected(request('status') === 'parcial')>Parcial</option>
                        <option value="pago" @selected(request('status') === 'pago')>Pago</option>
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
                        <th>Paciente</th>
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
                            $status = $conta->status;
                            $labels = ['aberta' => 'Aberto', 'parcial' => 'Parcial', 'pago' => 'Pago'];
                            $classes = ['aberta' => 'warning', 'parcial' => 'primary', 'pago' => 'success'];
                        @endphp
                        <tr @class(['table-success' => $conta->status === 'pago'])>
                            <td>{{ $conta->paciente?->nome ?? '—' }}</td>
                            <td>{{ $conta->vencimento?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-end">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($conta->valor_pago ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($conta->saldo, 2, ',', '.') }}</td>
                            <td><span class="badge text-bg-{{ $classes[$conta->status] ?? 'secondary' }}">{{ $labels[$conta->status] ?? $conta->status }}</span></td>
                            <td class="text-end">
                                @can('financeiro.receive')
                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#pagamento-{{ $conta->id }}" aria-expanded="false" title="Registrar pagamento">
                                        <i class="bi bi-cash-coin"></i>
                                    </button>
                                @endcan
                            </td>
                        </tr>
                        @can('financeiro.receive')
                            <tr class="collapse @error('valor') show @enderror" id="pagamento-{{ $conta->id }}">
                                <td colspan="7" class="bg-light">
                                    <form method="POST" action="{{ route('financeiro.receive', $conta) }}" class="row g-2 align-items-end">
                                        @csrf
                                        <div class="col-md-2">
                                            <label class="form-label">Valor *</label>
                                            <input type="number" step="0.01" min="0.01" name="valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor') }}" required>
                                            @error('valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Forma de pagamento *</label>
                                            <select name="payment_method_id" class="form-select @error('payment_method_id') is-invalid @enderror" required>
                                                <option value="">Selecione...</option>
                                                @foreach ($formasPagamento as $forma)
                                                    <option value="{{ $forma->id }}" @selected(old('payment_method_id') == $forma->id)>{{ $forma->label }}</option>
                                                @endforeach
                                            </select>
                                            @error('payment_method_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Data</label>
                                            <input type="date" name="data" class="form-control" value="{{ old('data', now()->format('Y-m-d')) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Observações</label>
                                            <input type="text" name="observacoes" class="form-control" value="{{ old('observacoes') }}">
                                        </div>
                                        <div class="col-md-2 d-grid">
                                            <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Receber</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endcan
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma conta a receber encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $contas->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('form[action*="/pagamentos"], form[method="GET"][action*="financeiro"]').forEach(function (form) {
            return;
        });
    </script>
@endpush
