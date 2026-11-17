@extends('layouts.app')

@section('title', 'Pacotes de laser')

@section('page-title', 'Regiões a Laser')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">Pacotes de laser</h1>
            <p class="text-muted small mb-0">
                Pacotes-modelo por região: sessões, valor e validade de referência. Nenhuma cobrança é gerada automaticamente.
            </p>
        </div>
    </div>

    @include('laser._tabs')

    @if ($regioes->isEmpty())
        <div class="alert alert-warning">
            Cadastre uma região antes de criar pacotes.
            <a href="{{ route('regioes_laser.index') }}" class="alert-link">Ir para regiões</a>.
        </div>
    @endif

    @can('regioes_laser.create')
        @if ($regioes->isNotEmpty())
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-plus-circle me-2"></i>Novo pacote
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('regioes_laser.pacotes.store') }}">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="regiao_id" class="form-label">Região *</label>
                                <select id="regiao_id" name="regiao_id"
                                        class="form-select @error('regiao_id') is-invalid @enderror" required>
                                    <option value="">Selecione…</option>
                                    @foreach ($regioes as $regiao)
                                        <option value="{{ $regiao->id }}" @selected((int) old('regiao_id') === $regiao->id)>
                                            {{ $regiao->nome }}{{ $regiao->is_active ? '' : ' (inativa)' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('regiao_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-4">
                                <label for="nome" class="form-label">Nome do pacote *</label>
                                <input type="text" id="nome" name="nome" maxlength="120"
                                       value="{{ old('nome') }}"
                                       class="form-control @error('nome') is-invalid @enderror"
                                       placeholder="Ex.: Axilas — 10 sessões" required>
                                @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-2">
                                <label for="total_sessoes" class="form-label">Sessões *</label>
                                <input type="number" id="total_sessoes" name="total_sessoes" min="1" max="100"
                                       value="{{ old('total_sessoes', 1) }}"
                                       class="form-control @error('total_sessoes') is-invalid @enderror" required>
                                @error('total_sessoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-2">
                                <label for="valor" class="form-label">Valor (R$)</label>
                                <input type="number" step="0.01" min="0" id="valor" name="valor"
                                       value="{{ old('valor', 0) }}"
                                       class="form-control @error('valor') is-invalid @enderror">
                                @error('valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-2">
                                <label for="validade_dias" class="form-label">Validade (dias)</label>
                                <input type="number" id="validade_dias" name="validade_dias" min="1" max="3650"
                                       value="{{ old('validade_dias') }}"
                                       class="form-control @error('validade_dias') is-invalid @enderror"
                                       placeholder="Opcional">
                                @error('validade_dias')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-8">
                                <label for="descricao" class="form-label">Descrição</label>
                                <input type="text" id="descricao" name="descricao" maxlength="1000"
                                       value="{{ old('descricao') }}"
                                       class="form-control @error('descricao') is-invalid @enderror"
                                       placeholder="Observações sobre o pacote">
                                @error('descricao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-4 d-flex align-items-end justify-content-between gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                           {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Pacote ativo</label>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>Salvar pacote
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Região</th>
                        <th>Pacote</th>
                        <th class="text-center">Sessões</th>
                        <th class="text-end">Valor</th>
                        <th class="text-center">Validade</th>
                        <th class="text-center">Aplicações</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pacotes as $pacote)
                        <tr>
                            <td class="fw-semibold">{{ $pacote->regiao?->nome ?? '—' }}</td>
                            <td>
                                {{ $pacote->nome }}
                                @if ($pacote->descricao)
                                    <div class="text-muted small">{{ $pacote->descricao }}</div>
                                @endif
                            </td>
                            <td class="text-center">{{ $pacote->total_sessoes }}</td>
                            <td class="text-end">{{ number_format((float) $pacote->valor, 2, ',', '.') }}</td>
                            <td class="text-center">{{ $pacote->validade_dias ? $pacote->validade_dias . ' dias' : '—' }}</td>
                            <td class="text-center">{{ $pacote->aplicacoes_count }}</td>
                            <td>
                                @if ($pacote->is_active)
                                    <span class="badge text-bg-success">Ativo</span>
                                @else
                                    <span class="badge text-bg-secondary">Inativo</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('regioes_laser.update')
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#pacoteEditar{{ $pacote->id }}">
                                        <i class="bi bi-pencil"></i><span class="visually-hidden">Editar {{ $pacote->nome }}</span>
                                    </button>
                                @endcan

                                @can('regioes_laser.delete')
                                    @if ($pacote->aplicacoes_count === 0)
                                        <form method="POST" action="{{ route('regioes_laser.pacotes.destroy', $pacote) }}"
                                              class="d-inline"
                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('Excluir o pacote '.$pacote->nome.'?') }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i><span class="visually-hidden">Excluir {{ $pacote->nome }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                                title="Este pacote já tem aplicações no histórico. Desative-o em vez de excluir.">
                                            <i class="bi bi-trash"></i><span class="visually-hidden">Excluir {{ $pacote->nome }}</span>
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Nenhum pacote cadastrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            {{ $pacotes->links() }}
        </div>
    </div>

    @can('regioes_laser.update')
        @foreach ($pacotes as $pacote)
            <div class="modal fade" id="pacoteEditar{{ $pacote->id }}" tabindex="-1"
                 aria-labelledby="pacoteEditarTitulo{{ $pacote->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('regioes_laser.pacotes.update', $pacote) }}">
                            @csrf
                            @method('PUT')

                            <div class="modal-header">
                                <h2 class="modal-title h5" id="pacoteEditarTitulo{{ $pacote->id }}">
                                    Editar pacote
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>

                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="regiao{{ $pacote->id }}" class="form-label">Região *</label>
                                    <select id="regiao{{ $pacote->id }}" name="regiao_id" class="form-select" required>
                                        @foreach ($regioes as $regiao)
                                            <option value="{{ $regiao->id }}" @selected($pacote->regiao_id === $regiao->id)>
                                                {{ $regiao->nome }}{{ $regiao->is_active ? '' : ' (inativa)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="nome{{ $pacote->id }}" class="form-label">Nome *</label>
                                    <input type="text" id="nome{{ $pacote->id }}" name="nome" maxlength="120"
                                           value="{{ $pacote->nome }}" class="form-control" required>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label for="sessoes{{ $pacote->id }}" class="form-label">Sessões *</label>
                                        <input type="number" id="sessoes{{ $pacote->id }}" name="total_sessoes"
                                               min="1" max="100" value="{{ $pacote->total_sessoes }}"
                                               class="form-control" required>
                                    </div>
                                    <div class="col-6">
                                        <label for="valor{{ $pacote->id }}" class="form-label">Valor (R$)</label>
                                        <input type="number" step="0.01" min="0" id="valor{{ $pacote->id }}" name="valor"
                                               value="{{ $pacote->valor }}" class="form-control">
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label for="validade{{ $pacote->id }}" class="form-label">Validade (dias)</label>
                                        <input type="number" id="validade{{ $pacote->id }}" name="validade_dias"
                                               min="1" max="3650" value="{{ $pacote->validade_dias }}" class="form-control">
                                    </div>
                                    <div class="col-6 d-flex align-items-end">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                   id="ativo{{ $pacote->id }}" name="is_active" value="1"
                                                   {{ $pacote->is_active ? 'checked' : '' }}>
                                            <label class="form-check-label" for="ativo{{ $pacote->id }}">Pacote ativo</label>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="descricao{{ $pacote->id }}" class="form-label">Descrição</label>
                                    <textarea id="descricao{{ $pacote->id }}" name="descricao" rows="2"
                                              maxlength="1000" class="form-control">{{ $pacote->descricao }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar alterações</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endcan
@endsection
