@extends('layouts.app')

@section('title', 'Financeiro — Pagamentos')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Pagamentos</h1>
            <p class="text-muted small mb-0">Recebimentos do caixa e vínculo dos lançamentos importados com o paciente.</p>
        </div>
        @can('financeiro.create')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#novoPagamento" aria-expanded="false">
                <i class="bi bi-plus-lg me-1"></i>Novo pagamento
            </button>
        @endcan
    </div>

    @include('financeiro.partials.nav')

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Recebido no total</div>
                    <div class="fs-5 fw-bold">R$ {{ number_format($resumo['total'], 2, ',', '.') }}</div>
                    <div class="text-muted small">{{ number_format($resumo['quantidade'], 0, ',', '.') }} lançamentos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small">Sem paciente vinculado</div>
                    <div class="fs-5 fw-bold text-warning">{{ number_format($resumo['sem_vinculo'], 0, ',', '.') }}</div>
                    <div class="text-muted small">R$ {{ number_format($resumo['sem_vinculo_valor'], 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    @can('financeiro.create')
        <div class="collapse mb-4 @error('valor') show @enderror" id="novoPagamento">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-2"></i>Lançar pagamento manual</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('financeiro.pagamentos.store') }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label for="paciente_id" class="form-label">Paciente *</label>
                            <select id="paciente_id" name="paciente_id" class="form-select @error('paciente_id') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                @foreach ($pacientes as $paciente)
                                    <option value="{{ $paciente->id }}" @selected(old('paciente_id') == $paciente->id)>{{ $paciente->nome }}</option>
                                @endforeach
                            </select>
                            @error('paciente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="descricao" class="form-label">Descrição *</label>
                            <input type="text" id="descricao" name="descricao" value="{{ old('descricao') }}" class="form-control @error('descricao') is-invalid @enderror" placeholder="Ex.: pacote de Pilates" required>
                            @error('descricao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="servico" class="form-label">Serviço</label>
                            <input type="text" id="servico" name="servico" value="{{ old('servico') }}" class="form-control" placeholder="Ex.: Pilates, laser, massagem">
                        </div>
                        <div class="col-md-2">
                            <label for="valor" class="form-label">Valor *</label>
                            <input type="number" step="0.01" min="0.01" id="valor" name="valor" value="{{ old('valor') }}" class="form-control @error('valor') is-invalid @enderror" required>
                            @error('valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="payment_method_id" class="form-label">Forma *</label>
                            <select id="payment_method_id" name="payment_method_id" class="form-select @error('payment_method_id') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                @foreach ($formasPagamento as $forma)
                                    <option value="{{ $forma->id }}" @selected(old('payment_method_id') == $forma->id)>{{ $forma->label }}</option>
                                @endforeach
                            </select>
                            @error('payment_method_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label for="data" class="form-label">Data *</label>
                            <input type="date" id="data" name="data" value="{{ old('data', now()->format('Y-m-d')) }}" class="form-control @error('data') is-invalid @enderror" required>
                            @error('data')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="observacoes" class="form-label">Observações</label>
                            <input type="text" id="observacoes" name="observacoes" value="{{ old('observacoes') }}" class="form-control">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Registrar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('financeiro.pagamentos.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="busca" class="form-label">Buscar</label>
                    <input type="search" id="busca" name="busca" class="form-control" value="{{ request('busca') }}" placeholder="Paciente, serviço ou observação...">
                </div>
                <div class="col-md-2">
                    <label for="vinculo" class="form-label">Vínculo</label>
                    <select id="vinculo" name="vinculo" class="form-select">
                        <option value="">Todos</option>
                        <option value="sem" @selected(request('vinculo') === 'sem')>Sem paciente</option>
                        <option value="com" @selected(request('vinculo') === 'com')>Com paciente</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="de" class="form-label">De</label>
                    <input type="date" id="de" name="de" class="form-control" value="{{ request('de') }}">
                </div>
                <div class="col-md-2">
                    <label for="ate" class="form-label">Até</label>
                    <input type="date" id="ate" name="ate" class="form-control" value="{{ request('ate') }}">
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
                        <th>Data</th>
                        <th>Paciente</th>
                        <th>Serviço / observação</th>
                        <th>Forma</th>
                        <th class="text-end">Valor</th>
                        <th>Situação</th>
                        <th class="text-end">Ações</th>
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
                            <td>
                                {{ $pagamento->servico ?: '—' }}
                                @if ($pagamento->observacoes)
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($pagamento->observacoes, 80) }}</div>
                                @endif
                            </td>
                            <td>{{ $pagamento->paymentMethod?->label ?? '—' }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format((float) $pagamento->valor, 2, ',', '.') }}</td>
                            <td>
                                @if ($pagamento->paciente_id)
                                    <span class="badge text-bg-success">Vinculado</span>
                                @else
                                    <span class="badge text-bg-warning">Revisar</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('financeiro.pagamentos.revisar', $pagamento) }}" class="btn btn-sm btn-outline-primary" title="Revisar vínculo">
                                    <i class="bi bi-person-check"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum pagamento encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $pagamentos->links() }}
        </div>
    </div>
@endsection
