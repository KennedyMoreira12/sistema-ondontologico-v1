@extends('layouts.app')

@section('title', 'Novo procedimento')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Novo procedimento</h1>
        <a href="{{ route('procedimentos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('procedimentos.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="codigo" class="form-label">Código</label>
                        <input type="text" id="codigo" name="codigo" class="form-control @error('codigo') is-invalid @enderror" value="{{ old('codigo') }}" maxlength="30">
                        @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" id="nome" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="categoria" class="form-label">Categoria</label>
                        <input type="text" id="categoria" name="categoria" class="form-control @error('categoria') is-invalid @enderror" value="{{ old('categoria') }}" maxlength="100">
                        @error('categoria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="duracao_minutos" class="form-label">Duração (min)</label>
                        <input type="number" id="duracao_minutos" name="duracao_minutos" class="form-control @error('duracao_minutos') is-invalid @enderror" value="{{ old('duracao_minutos') }}" min="1">
                        @error('duracao_minutos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="valor_padrao" class="form-label">Valor padrão (R$)</label>
                        <input type="number" id="valor_padrao" name="valor_padrao" class="form-control @error('valor_padrao') is-invalid @enderror" value="{{ old('valor_padrao', '0,00') }}" min="0" step="0.01">
                        @error('valor_padrao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="is_active" class="form-label">Status</label>
                        <select id="is_active" name="is_active" class="form-select">
                            <option value="1" @selected(old('is_active', '1') === '1')>Ativo</option>
                            <option value="0" @selected(old('is_active') === '0')>Inativo</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea id="descricao" name="descricao" class="form-control @error('descricao') is-invalid @enderror" rows="3">{{ old('descricao') }}</textarea>
                        @error('descricao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                    <a href="{{ route('procedimentos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection