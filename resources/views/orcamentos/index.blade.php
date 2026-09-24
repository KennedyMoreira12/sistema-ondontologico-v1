@extends('layouts.app')

@section('title', 'Orçamentos')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Orçamentos</h1>
                <div class="text-muted small">
                    {{ $paciente->nome }}
                    @if ($paciente->status === 'ativo')
                        <span class="badge bg-success ms-1">Ativo</span>
                    @else
                        <span class="badge bg-secondary ms-1">Inativo</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('orcamentos.create')
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#novoOrcamentoCard" aria-expanded="false">
                    <i class="bi bi-plus-lg me-1"></i>Novo orçamento
                </button>
            @endcan
        </div>
    </div>

    @can('orcamentos.create')
        <div class="collapse mb-4" id="novoOrcamentoCard">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('pacientes.orcamentos.store', $paciente) }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label for="titulo" class="form-label">Título do orçamento *</label>
                            <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Ex.: Orçamento reabilitação" value="{{ old('titulo') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label for="validade" class="form-label">Validade</label>
                            <input type="date" id="validade" name="validade" class="form-control" value="{{ old('validade') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="observacoes" class="form-label">Observações</label>
                            <input type="text" id="observacoes" name="observacoes" class="form-control" placeholder="Condições do pagamento etc." value="{{ old('observacoes') }}">
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Orçamento</th>
                        <th>Responsável</th>
                        <th>Status</th>
                        <th class="text-end">Itens</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orcamentos as $orcamento)
                        <tr>
                            <td>{{ $orcamento->id }}</td>
                            <td>
                                <a href="{{ route('pacientes.orcamentos.show', [$paciente, $orcamento]) }}" class="fw-semibold text-decoration-none">
                                    {{ $orcamento->titulo ?? 'Orçamento #'.$orcamento->id }}
                                </a>
                            </td>
                            <td>{{ $orcamento->profissional?->nome ?? '—' }}</td>
                            <td>
                                @php
                                    $cores = [
                                        'rascunho' => '#6c757d',
                                        'emitido' => '#0d6efd',
                                        'aprovado' => '#198754',
                                        'recusado' => '#dc3545',
                                        'cancelado' => '#6c757d',
                                    ];
                                    $labels = [
                                        'rascunho' => 'Rascunho',
                                        'emitido' => 'Emitido',
                                        'aprovado' => 'Aprovado',
                                        'recusado' => 'Recusado',
                                        'cancelado' => 'Cancelado',
                                    ];
                                @endphp
                                <span class="badge" style="background-color: {{ $cores[$orcamento->status] ?? '#6c757d' }}">
                                    {{ $labels[$orcamento->status] ?? $orcamento->status }}
                                </span>
                            </td>
                            <td class="text-end">{{ $orcamento->items_count }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($orcamento->total, 2, ',', '.') }}</td>
                            <td class="text-end">
                                <a href="{{ route('pacientes.orcamentos.show', [$paciente, $orcamento]) }}" class="btn btn-sm btn-outline-primary" title="Abrir orçamento"><i class="bi bi-folder2-open"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Nenhum orçamento para este paciente.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection