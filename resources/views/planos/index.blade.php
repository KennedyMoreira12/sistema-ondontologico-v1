@extends('layouts.app')

@section('title', 'Planos de tratamento')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Planos de tratamento</h1>
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
            @can('tratamentos.create')
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#novoPlanoCard" aria-expanded="false">
                    <i class="bi bi-plus-lg me-1"></i>Novo plano
                </button>
            @endcan
        </div>
    </div>

    @can('tratamentos.create')
        <div class="collapse mb-4" id="novoPlanoCard">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('pacientes.planos.store', $paciente) }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label for="titulo" class="form-label">Título do plano *</label>
                            <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Ex.: Reabilitação dentária" value="{{ old('titulo') }}" required>
                        </div>
                        <div class="col-md-5">
                            <label for="descricao" class="form-label">Descrição</label>
                            <input type="text" id="descricao" name="descricao" class="form-control" placeholder="Objetivo / resumo do tratamento" value="{{ old('descricao') }}">
                        </div>
                        <div class="col-md-2">
                            <label for="desconto" class="form-label">Desconto (R$)</label>
                            <input type="number" id="desconto" name="desconto" class="form-control" min="0" step="0.01" value="0">
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
                        <th>Plano</th>
                        <th>Responsável</th>
                        <th>Status</th>
                        <th class="text-end">Itens</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($planos as $plano)
                        <tr>
                            <td>{{ $plano->id }}</td>
                            <td>
                                <a href="{{ route('pacientes.planos.show', [$paciente, $plano]) }}" class="fw-semibold text-decoration-none">
                                    {{ $plano->titulo }}
                                </a>
                                @if ($plano->descricao)
                                    <div class="text-muted small">{{ $plano->descricao }}</div>
                                @endif
                            </td>
                            <td>{{ $plano->profissional?->nome ?? '—' }}</td>
                            <td>
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
                                <span class="badge" style="background-color: {{ $cores[$plano->status] ?? '#6c757d' }}">
                                    {{ $labels[$plano->status] ?? $plano->status }}
                                </span>
                            </td>
                            <td class="text-end">{{ $plano->items_count }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($plano->total, 2, ',', '.') }}</td>
                            <td class="text-end">
                                <a href="{{ route('pacientes.planos.show', [$paciente, $plano]) }}" class="btn btn-sm btn-outline-primary" title="Abrir plano"><i class="bi bi-folder2-open"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Nenhum plano de tratamento para este paciente.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection