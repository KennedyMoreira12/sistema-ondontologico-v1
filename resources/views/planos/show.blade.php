@extends('layouts.app')

@section('title', $plano->titulo)

@php
    $cores = [
        'rascunho' => '#6c757d',
        'aprovado' => '#0d6efd',
        'em_andamento' => '#ffc107',
        'concluido' => '#198754',
        'cancelado' => '#dc3545',
    ];
    $labels = [
        'rascunho' => 'Rascunho',
        'aprovado' => 'Aprovado',
        'em_andamento' => 'Em andamento',
        'concluido' => 'Concluído',
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
            <a href="{{ route('pacientes.planos.index', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">{{ $plano->titulo }}</h1>
                <div class="text-muted small">{{ $paciente->nome }} • #{{ $plano->id }}</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('tratamentos.view')
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
            @endcan
            @can('tratamentos.delete')
                <form method="POST" action="{{ route('pacientes.planos.destroy', [$paciente, $plano]) }}" onsubmit="return confirm('Excluir este plano de tratamento e todos os seus itens?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Excluir</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="sco-print-only mb-3">
        <div class="fw-bold fs-5">{{ $plano->titulo }}</div>
        <div>Paciente: {{ $paciente->nome }} — {{ $paciente->sexo === 'masculino' ? 'Masculino' : ($paciente->sexo === 'feminino' ? 'Feminino' : '—') }}, {{ $paciente->nascimento?->translatedFormat('d/m/Y') ?: '—' }}</div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold"><i class="bi bi-clipboard2-pulse me-2"></i>Sobre o plano</span>
                    @can('tratamentos.update')
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editarPlanoCard"><i class="bi bi-pencil me-1"></i>Editar</button>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Responsável</span>
                            <strong>{{ $plano->profissional?->nome ?? '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Status</span>
                            <span class="badge" style="background-color: {{ $cores[$plano->status] ?? '#6c757d' }}">
                                {{ $labels[$plano->status] ?? $plano->status }}
                            </span>
                        </div>
                        @if ($plano->descricao)
                            <div class="col-12">
                                <span class="text-muted d-block small">Descrição</span>
                                <p class="mb-0">{{ $plano->descricao }}</p>
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
                        <span>R$ {{ number_format($plano->items->sum('valor_total'), 2, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Desconto</span>
                        <span class="text-danger">- R$ {{ number_format($plano->desconto, 2, ',', '.') }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span>R$ {{ number_format($plano->total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('tratamentos.update')
        <div class="collapse mb-4" id="editarPlanoCard">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('pacientes.planos.update', [$paciente, $plano]) }}" class="row g-3 align-items-end">
                        @csrf
                        @method('PUT')
                        <div class="col-md-4">
                            <label for="titulo" class="form-label">Título *</label>
                            <input type="text" id="titulo" name="titulo" class="form-control" value="{{ old('titulo', $plano->titulo) }}" required>
                        </div>
                        <div class="col-md-5">
                            <label for="descricao" class="form-label">Descrição</label>
                            <input type="text" id="descricao" name="descricao" class="form-control" value="{{ old('descricao', $plano->descricao) }}">
                        </div>
                        <div class="col-md-2">
                            <label for="desconto" class="form-label">Desconto (R$)</label>
                            <input type="number" id="desconto" name="desconto" class="form-control" min="0" step="0.01" value="{{ old('desconto', $plano->desconto) }}">
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 sco-print-hide">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-arrow-repeat me-2"></i>Status do tratamento</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pacientes.planos.status', [$paciente, $plano]) }}" class="row g-2 align-items-end">
                    @csrf
                    @method('PATCH')
                    <div class="col-md-4">
                        <label for="status" class="form-label">Mover para</label>
                        <select id="status" name="status" class="form-select">
                            @foreach ($labels as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected($valor === $plano->status)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Aplicar</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-2"></i>Procedimentos do plano</div>

        @can('tratamentos.create')
            <div class="card-body border-bottom sco-print-hide">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#novoItemCard" aria-expanded="false">
                    <i class="bi bi-plus-lg me-1"></i>Adicionar procedimento
                </button>

                <div class="collapse mt-3" id="novoItemCard">
                    <form method="POST" action="{{ route('pacientes.planos.items.store', [$paciente, $plano]) }}" class="row g-3 align-items-end">
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
                            <label for="item_observacao" class="form-label">Observação</label>
                            <input type="text" id="item_observacao" name="observacao" class="form-control">
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
                        <th>Status</th>
                        <th>Observação</th>
                        <th class="text-end sco-print-hide">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plano->items as $item)
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
                            <td>
                                @if ($item->status === 'executado')
                                    <span class="badge bg-success">Executado</span>
                                @else
                                    <span class="badge bg-secondary">Planejado</span>
                                @endif
                            </td>
                            <td>
                                <span class="small text-muted">{{ $item->observacao ?? '—' }}</span>
                            </td>
                            <td class="text-end sco-print-hide">
                                <div class="d-flex justify-content-end gap-1">
                                    @can('tratamentos.update')
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editarItem{{ $item->id }}" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('pacientes.planos.items.status', [$paciente, $plano, $item]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $item->status === 'executado' ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                                title="{{ $item->status === 'executado' ? 'Reverter para planejado' : 'Marcar como executado' }}">
                                                <i class="bi {{ $item->status === 'executado' ? 'bi-arrow-counterclockwise' : 'bi-check-lg' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                    @can('tratamentos.delete')
                                        <form method="POST" action="{{ route('pacientes.planos.items.destroy', [$paciente, $plano, $item]) }}" onsubmit="return confirm('Remover este procedimento do plano?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @can('tratamentos.update')
                            <tr class="collapse sco-print-hide" id="editarItem{{ $item->id }}">
                                <td colspan="9" class="bg-light p-3">
                                    <form method="POST" action="{{ route('pacientes.planos.items.update', [$paciente, $plano, $item]) }}" class="row g-3 align-items-end">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-md-3">
                                            <label class="form-label">Procedimento *</label>
                                            <select name="procedure_id" class="form-select">
                                                @foreach ($procedimentos as $procedimento)
                                                    <option value="{{ $procedimento->id }}" data-valor="{{ $procedimento->valor_padrao }}" @selected($item->procedure_id === $procedimento->id)>
                                                        {{ $procedimento->nome }}{{ $procedimento->codigo ? ' ('.$procedimento->codigo.')' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Dente</label>
                                            <input type="number" name="dente" class="form-control" min="11" max="48" value="{{ $item->dente }}">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Qtd</label>
                                            <input type="number" name="quantidade" class="form-control" min="1" value="{{ $item->quantidade }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Valor unit. (R$) *</label>
                                            <input type="number" name="valor_unitario" class="form-control valor-item" min="0" step="0.01" value="{{ $item->valor_unitario }}" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Desc. (R$)</label>
                                            <input type="number" name="desconto" class="form-control" min="0" step="0.01" value="{{ $item->desconto }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Observação</label>
                                            <input type="text" name="observacao" class="form-control" value="{{ $item->observacao }}">
                                        </div>
                                        <div class="col-md-1 d-grid">
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i></button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endcan
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                Nenhum procedimento no plano ainda.
                                @can('tratamentos.create')
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#novoItemCard">
                                            <i class="bi bi-plus-lg me-1"></i>Adicionar o primeiro procedimento
                                        </button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('#novoItemCard select[name="procedure_id"]').forEach((select) => {
                select.addEventListener('change', () => {
                    const opt = select.options[select.selectedIndex];
                    const valor = opt ? opt.dataset.valor : null;
                    if (valor !== null && valor !== undefined) {
                        document.getElementById('item_valor_unitario').value = valor;
                    }
                });
            });
        </script>
    @endpush
@endsection