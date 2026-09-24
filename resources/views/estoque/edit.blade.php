@extends('layouts.app')

@section('title', 'Editar produto')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Editar produto</h1>
        <a href="{{ route('estoque.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('estoque.update', $produto) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" id="nome" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $produto->nome) }}" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="codigo" class="form-label">Código</label>
                        <input type="text" id="codigo" name="codigo" class="form-control @error('codigo') is-invalid @enderror" value="{{ old('codigo', $produto->codigo) }}" maxlength="50">
                        @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="unidade" class="form-label">Unidade</label>
                        <input type="text" id="unidade" name="unidade" class="form-control" value="{{ old('unidade', $produto->unidade) }}" maxlength="20">
                    </div>
                    <div class="col-md-4">
                        <label for="product_category_id" class="form-label">Categoria</label>
                        <select id="product_category_id" name="product_category_id" class="form-select @error('product_category_id') is-invalid @enderror">
                            <option value="">Sem categoria</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->id }}" @selected(old('product_category_id', $produto->product_category_id) == $categoria->id)>{{ $categoria->name }}</option>
                            @endforeach
                        </select>
                        @error('product_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="supplier_id" class="form-label">Fornecedor</label>
                        <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">Sem fornecedor</option>
                            @foreach ($fornecedores as $fornecedor)
                                <option value="{{ $fornecedor->id }}" @selected(old('supplier_id', $produto->supplier_id) == $fornecedor->id)>{{ $fornecedor->nome }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="lote" class="form-label">Lote</label>
                        <input type="text" id="lote" name="lote" class="form-control" value="{{ old('lote', $produto->lote) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="estoque" class="form-label">Estoque atual</label>
                        <input type="number" step="0.001" min="0" id="estoque" name="estoque" class="form-control" value="{{ old('estoque', $produto->estoque) }}">
                        <div class="form-text">Use o botão de movimentação na listagem para registrar entradas/saídas.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="estoque_minimo" class="form-label">Estoque mínimo</label>
                        <input type="number" step="0.001" min="0" id="estoque_minimo" name="estoque_minimo" class="form-control" value="{{ old('estoque_minimo', $produto->estoque_minimo) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="custo" class="form-label">Custo (R$)</label>
                        <input type="text" id="custo" name="custo" class="form-control" value="{{ old('custo', number_format($produto->custo, 2, ',', '')) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="preco" class="form-label">Preço (R$)</label>
                        <input type="text" id="preco" name="preco" class="form-control" value="{{ old('preco', number_format($produto->preco, 2, ',', '')) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="validade" class="form-label">Validade</label>
                        <input type="date" id="validade" name="validade" class="form-control" value="{{ old('validade', $produto->validade?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Controle de estoque</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="controlar_estoque" name="controlar_estoque" value="1" @checked(old('controlar_estoque', $produto->controlar_estoque ? '1' : '0') === '1')>
                            <label class="form-check-label" for="controlar_estoque">Controlar estoque</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="is_active" class="form-label">Status</label>
                        <select id="is_active" name="is_active" class="form-select">
                            <option value="1" @selected(old('is_active', $produto->is_active ? '1' : '0') === '1')>Ativo</option>
                            <option value="0" @selected(old('is_active', $produto->is_active ? '1' : '0') === '0')>Inativo</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                    <a href="{{ route('estoque.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection