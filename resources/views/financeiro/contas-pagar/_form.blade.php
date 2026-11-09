@php
    /** @var \App\Models\AccountsPayable|null $conta */
    $conta = $conta ?? null;
    $edicao = $conta !== null;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="nome_conta" class="form-label">Nome da conta *</label>
        <input type="text" id="nome_conta" name="nome_conta" value="{{ old('nome_conta', $conta?->nome_conta) }}" class="form-control @error('nome_conta') is-invalid @enderror" placeholder="Ex.: Aluguel da sala, energia, fornecedor..." required>
        @error('nome_conta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="valor" class="form-label">Valor *</label>
        <input type="number" step="0.01" min="0.01" id="valor" name="valor" value="{{ old('valor', $conta?->valor) }}" class="form-control @error('valor') is-invalid @enderror" required>
        @error('valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="data_vencimento" class="form-label">Vencimento *</label>
        <input type="date" id="data_vencimento" name="data_vencimento" value="{{ old('data_vencimento', $conta?->data_vencimento?->format('Y-m-d')) }}" class="form-control @error('data_vencimento') is-invalid @enderror" required>
        @error('data_vencimento')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="supplier_id" class="form-label">Fornecedor</label>
        <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
            <option value="">Sem fornecedor</option>
            @foreach ($fornecedores as $fornecedor)
                <option value="{{ $fornecedor->id }}" @selected(old('supplier_id', $conta?->supplier_id) == $fornecedor->id)>{{ $fornecedor->nome }}</option>
            @endforeach
        </select>
        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="professional_id" class="form-label">Profissional externo</label>
        <select id="professional_id" name="professional_id" class="form-select @error('professional_id') is-invalid @enderror">
            <option value="">Sem profissional</option>
            @foreach ($profissionais as $profissional)
                <option value="{{ $profissional->id }}" @selected(old('professional_id', $conta?->professional_id) == $profissional->id)>{{ $profissional->nome }}</option>
            @endforeach
        </select>
        @error('professional_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="tipo_remuneracao" class="form-label">Remuneração</label>
        <select id="tipo_remuneracao" name="tipo_remuneracao" class="form-select @error('tipo_remuneracao') is-invalid @enderror">
            <option value="">Não informada</option>
            @foreach (\App\Models\AccountsPayable::TIPOS_REMUNERACAO as $remuneracao)
                <option value="{{ $remuneracao }}" @selected(old('tipo_remuneracao', $conta?->tipo_remuneracao) === $remuneracao)>{{ ucfirst(str_replace('_', ' ', $remuneracao)) }}</option>
            @endforeach
        </select>
        @error('tipo_remuneracao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="financeiro_categoria_id" class="form-label">Categoria</label>
        <select id="financeiro_categoria_id" name="financeiro_categoria_id" class="form-select @error('financeiro_categoria_id') is-invalid @enderror">
            <option value="">Sem categoria</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected(old('financeiro_categoria_id', $conta?->financeiro_categoria_id) == $categoria->id)>{{ $categoria->nome }}</option>
            @endforeach
        </select>
        @error('financeiro_categoria_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="tipo" class="form-label">Tipo *</label>
        <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
            @foreach (\App\Models\AccountsPayable::TIPOS as $tipo)
                <option value="{{ $tipo }}" @selected(old('tipo', $conta?->tipo ?? 'outros') === $tipo)>{{ ucfirst($tipo) }}</option>
            @endforeach
        </select>
        @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="tipo_documento" class="form-label">Documento *</label>
        <select id="tipo_documento" name="tipo_documento" class="form-select @error('tipo_documento') is-invalid @enderror" required>
            @foreach (\App\Models\AccountsPayable::TIPOS_DOCUMENTO as $documento)
                <option value="{{ $documento }}" @selected(old('tipo_documento', $conta?->tipo_documento ?? 'outros') === $documento)>{{ strtoupper($documento) }}</option>
            @endforeach
        </select>
        @error('tipo_documento')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea id="descricao" name="descricao" rows="2" class="form-control @error('descricao') is-invalid @enderror">{{ old('descricao', $conta?->descricao) }}</textarea>
        @error('descricao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea id="observacoes" name="observacoes" rows="2" class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes', $conta?->observacoes) }}</textarea>
        @error('observacoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
