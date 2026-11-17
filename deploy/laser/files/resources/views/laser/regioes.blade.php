@extends('layouts.app')

@section('title', 'Regiões a Laser')

@section('page-title', 'Regiões a Laser')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">Regiões a Laser</h1>
            <p class="text-muted small mb-0">
                Regiões corporais, pacotes-modelo e histórico de aplicações. Nenhuma cobrança é gerada automaticamente.
            </p>
        </div>
    </div>

    @include('laser._tabs')

    @can('regioes_laser.create')
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-plus-circle me-2"></i>Nova região
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('regioes_laser.store') }}">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="nome" class="form-label">Nome da região *</label>
                            <input type="text" id="nome" name="nome" maxlength="120"
                                   value="{{ old('nome') }}"
                                   class="form-control @error('nome') is-invalid @enderror"
                                   placeholder="Ex.: Axilas" required>
                            @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-2">
                            <label for="ordem" class="form-label">Ordem</label>
                            <input type="number" id="ordem" name="ordem" min="0" max="999"
                                   value="{{ old('ordem', 0) }}"
                                   class="form-control @error('ordem') is-invalid @enderror">
                            @error('ordem')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-5">
                            <label for="descricao" class="form-label">Descrição</label>
                            <input type="text" id="descricao" name="descricao" maxlength="1000"
                                   value="{{ old('descricao') }}"
                                   class="form-control @error('descricao') is-invalid @enderror"
                                   placeholder="Observações sobre a região">
                            @error('descricao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                       {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Região ativa</label>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Salvar região
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Região</th>
                        <th>Descrição</th>
                        <th class="text-center">Pacotes</th>
                        <th class="text-center">Aplicações</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($regioes as $regiao)
                        <tr>
                            <td class="fw-semibold">{{ $regiao->nome }}</td>
                            <td class="text-muted small">{{ $regiao->descricao ?: '—' }}</td>
                            <td class="text-center">{{ $regiao->pacotes_count }}</td>
                            <td class="text-center">{{ $regiao->aplicacoes_count }}</td>
                            <td>
                                @if ($regiao->is_active)
                                    <span class="badge text-bg-success">Ativa</span>
                                @else
                                    <span class="badge text-bg-secondary">Inativa</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('regioes_laser.update')
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#regiaoEditar{{ $regiao->id }}">
                                        <i class="bi bi-pencil"></i><span class="visually-hidden">Editar {{ $regiao->nome }}</span>
                                    </button>
                                @endcan

                                @can('regioes_laser.delete')
                                    @if ($regiao->pacotes_count === 0 && $regiao->aplicacoes_count === 0)
                                        <form method="POST" action="{{ route('regioes_laser.destroy', $regiao) }}"
                                              class="d-inline"
                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('Excluir a região '.$regiao->nome.'?') }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i><span class="visually-hidden">Excluir {{ $regiao->nome }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                                title="Há pacotes ou aplicações vinculados. Desative a região em vez de excluir.">
                                            <i class="bi bi-trash"></i><span class="visually-hidden">Excluir {{ $regiao->nome }}</span>
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Nenhuma região cadastrada. Comece cadastrando a primeira região corporal.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            {{ $regioes->links() }}
        </div>
    </div>

    @can('regioes_laser.update')
        @foreach ($regioes as $regiao)
            <div class="modal fade" id="regiaoEditar{{ $regiao->id }}" tabindex="-1"
                 aria-labelledby="regiaoEditarTitulo{{ $regiao->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('regioes_laser.update', $regiao) }}">
                            @csrf
                            @method('PUT')

                            <div class="modal-header">
                                <h2 class="modal-title h5" id="regiaoEditarTitulo{{ $regiao->id }}">
                                    Editar região
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>

                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="nome{{ $regiao->id }}" class="form-label">Nome *</label>
                                    <input type="text" id="nome{{ $regiao->id }}" name="nome" maxlength="120"
                                           value="{{ $regiao->nome }}" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label for="descricao{{ $regiao->id }}" class="form-label">Descrição</label>
                                    <textarea id="descricao{{ $regiao->id }}" name="descricao" rows="2"
                                              maxlength="1000" class="form-control">{{ $regiao->descricao }}</textarea>
                                </div>

                                <div class="row g-3">
                                    <div class="col-5">
                                        <label for="ordem{{ $regiao->id }}" class="form-label">Ordem</label>
                                        <input type="number" id="ordem{{ $regiao->id }}" name="ordem"
                                               min="0" max="999" value="{{ $regiao->ordem }}" class="form-control">
                                    </div>
                                    <div class="col-7 d-flex align-items-end">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                   id="ativo{{ $regiao->id }}" name="is_active" value="1"
                                                   {{ $regiao->is_active ? 'checked' : '' }}>
                                            <label class="form-check-label" for="ativo{{ $regiao->id }}">Região ativa</label>
                                        </div>
                                    </div>
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
