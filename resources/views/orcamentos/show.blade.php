@extends('layouts.app')

@section('title', $orcamento->titulo ?? 'Orçamento #'.$orcamento->id)

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

@section('content')
    <style>
        @media print {
            .sco-sidebar, .sco-topbar, .sco-print-hide { display: none !important; }
            .sco-main { margin-left: 0 !important; }
            .sco-content { padding: 0 !important; }
            body { background: #fff !important; }
            .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
            .sco-print-only { display: block !important; }
        }
        .sco-print-only { display: none; }
    </style>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4 sco-print-hide">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.orcamentos.index', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">{{ $orcamento->titulo ?? 'Orçamento #'.$orcamento->id }}</h1>
                <div class="text-muted small">{{ $paciente->nome }} • #{{ $orcamento->id }}</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('orcamentos.view')
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
            @endcan
            @can('orcamentos.delete')
                <form method="POST" action="{{ route('pacientes.orcamentos.destroy', [$paciente, $orcamento]) }}" onsubmit="return confirm('Excluir este orçamento e todos os seus itens?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Excluir</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="sco-print-only mb-3">
        <div class="fw-bold fs-5">{{ $orcamento->titulo ?? 'Orçamento #'.$orcamento->id }}</div>
        <div>Paciente: {{ $paciente->nome }}</div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="bi bi-file-earmark-text me-2"></i>Sobre o orçamento</span>
                    @can('orcamentos.update')
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editarOrcamentoCard"><i class="bi bi-pencil me-1"></i>Editar</button>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Responsável</span>
                            <strong>{{ $orcamento->profissional?->nome ?? '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Status</span>
                            <span class="badge" style="background-color: {{ $cores[$orcamento->status] ?? '#6c757d' }}">
                                {{ $labels[$orcamento->status] ?? $orcamento->status }}
                            </span>
                        </div>
                        @if ($orcamento->validade)
                            <div class="col-md-6">
                                <span class="text-muted d-block small">Validade</span>
                                <strong>{{ $orcamento->validade->translatedFormat('d/m/Y') }}</strong>
                            </div>
                        @endif
                        @if ($orcamento->observacoes)
                            <div class="col-12">
                                <span class="text-muted d-block small">Observações</span>
                                <p class="mb-0">{{ $orcamento->observacoes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-calculator me-2"></i>Valores</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Subtotal (procedimentos)</span>
                        <span>R$ {{ number_format($orcamento->items->sum('valor_total'), 2, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Desconto</span>
                        <span class="text-danger">- R$ {{ number_format($orcamento->desconto, 2, ',', '.') }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span>R$ {{ number_format($orcamento->total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('orcamentos.update')
        <div class="collapse mb-4" id="editarOrcamentoCard">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('pacientes.orcamentos.update', [$paciente, $orcamento]) }}" class="row g-3 align-items-end">
                        @csrf
                        @method('PUT')
                        <div class="col-md-4">
                            <label for="titulo" class="form-label">Título *</label>
                            <input type="text" id="titulo" name="titulo" class="form-control" value="{{ old('titulo', $orcamento->titulo) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label for="validade" class="form-label">Validade</label>
                            <input type="date" id="validade" name="validade" class="form-control" value="{{ old('validade', $orcamento->validade?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                @foreach ($labels as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected($valor === $orcamento->status)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i></button>
                        </div>
                        <div class="col-12">
                            <label for="observacoes" class="form-label">Observações</label>
                            <input type="text" id="observacoes" name="observacoes" class="form-control" value="{{ old('observacoes', $orcamento->observacoes) }}">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-2"></i>Procedimentos do orçamento</div>

        @can('orcamentos.create')
            <div class="card-body border-bottom sco-print-hide">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#novoItemCard" aria-expanded="false">
                    <i class="bi bi-plus-lg me-1"></i>Adicionar procedimento
                </button>

                <div class="collapse mt-3" id="novoItemCard">
                    <form method="POST" action="{{ route('pacientes.orcamentos.items.store', [$paciente, $orcamento]) }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-3">
                            <label for="item_procedure_id" class="form-label">Procedimento *</label>
                            <select id="item_procedure_id" name="procedure_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach ($procedimentos as $procedimento)
                                    <option value="{{ $procedimento->id }}" data-valor="{{ $procedimento->valor_padrao }}">
                                        {{ $procedimento->nome }}{{ $procedimento->codigo ? ' ('.$procedimento->codigo.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="item_dente" class="form-label">Dente</label>
                            <input type="number" id="item_dente" name="dente" class="form-control" min="11" max="48" placeholder="Ex.: 26">
                        </div>
                        <div class="col-md-1">
                            <label for="item_quantidade" class="form-label">Qtd</label>
                            <input type="number" id="item_quantidade" name="quantidade" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-2">
                            <label for="item_valor_unitario" class="form-label">Valor unit. (R$) *</label>
                            <input type="number" id="item_valor_unitario" name="valor_unitario" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-1">
                            <label for="item_desconto" class="form-label">Desc. (R$)</label>
                            <input type="number" id="item_desconto" name="desconto" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-2">
                            <label for="item_descricao" class="form-label">Observação</label>
                            <input type="text" id="item_descricao" name="descricao" class="form-control">
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Procedimento</th>
                        <th>Dente</th>
                        <th class="text-center">Qtd</th>
                        <th class="text-end">Valor unit.</th>
                        <th class="text-end">Desc.</th>
                        <th class="text-end">Total</th>
                        <th class="text-end sco-print-hide">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orcamento->items as $item)
                        <tr>
                            <td>
                                {{ $item->procedure?->nome ?? 'Procedimento removido' }}
                                @if ($item->procedure?->codigo)
                                    <span class="text-muted small">({{ $item->procedure->codigo }})</span>
                                @endif
                            </td>
                            <td>{{ $item->dente ?? '—' }}</td>
                            <td class="text-center">{{ $item->quantidade }}</td>
                            <td class="text-end">R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                            <td class="text-end text-danger">{{ $item->desconto ? 'R$ '.number_format($item->desconto, 2, ',', '.') : '—' }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($item->valor_total, 2, ',', '.') }}</td>
                            <td class="text-end sco-print-hide">
                                @can('orcamentos.delete')
                                    <form method="POST" action="{{ route('pacientes.orcamentos.items.destroy', [$paciente, $orcamento, $item]) }}" class="d-inline" onsubmit="return confirm('Remover este item?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum procedimento adicionado ao orçamento.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection