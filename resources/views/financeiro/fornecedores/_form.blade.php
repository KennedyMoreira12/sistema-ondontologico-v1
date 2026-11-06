@php
    /** @var \App\Models\Supplier|null $fornecedor */
    $fornecedor = $fornecedor ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-8">
        <label for="nome" class="form-label">Nome *</label>
        <input type="text" id="nome" name="nome" value="{{ old('nome', $fornecedor?->nome) }}" class="form-control @error('nome') is-invalid @enderror" required>
        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="documento" class="form-label">CPF/CNPJ</label>
        <input type="text" id="documento" name="documento" value="{{ old('documento', $fornecedor?->documento) }}" class="form-control @error('documento') is-invalid @enderror">
        @error('documento')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="contato" class="form-label">Contato</label>
        <input type="text" id="contato" name="contato" value="{{ old('contato', $fornecedor?->contato) }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label for="phone" class="form-label">Telefone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $fornecedor?->phone) }}" class="form-control @error('phone') is-invalid @enderror">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="email" class="form-label">E-mail</label>
        <input type="email" id="email" name="email" value="{{ old('email', $fornecedor?->email) }}" class="form-control @error('email') is-invalid @enderror">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label for="endereco" class="form-label">Endereço</label>
        <input type="text" id="endereco" name="endereco" value="{{ old('endereco', $fornecedor?->endereco) }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label for="tipo_documento" class="form-label">Documento padrão *</label>
        <select id="tipo_documento" name="tipo_documento" class="form-select @error('tipo_documento') is-invalid @enderror" required>
            @foreach (['nf', 'boleto', 'duplicata', 'outros'] as $documento)
                <option value="{{ $documento }}" @selected(old('tipo_documento', $fornecedor?->tipo_documento ?? 'outros') === $documento)>{{ strtoupper($documento) }}</option>
            @endforeach
        </select>
        @error('tipo_documento')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea id="observacoes" name="observacoes" rows="2" class="form-control">{{ old('observacoes', $fornecedor?->observacoes) }}</textarea>
    </div>
    <div class="col-md-3">
        <label for="is_active" class="form-label">Status</label>
        <select id="is_active" name="is_active" class="form-select">
            <option value="1" @selected(old('is_active', $fornecedor?->is_active ?? true) == true)>Ativo</option>
            <option value="0" @selected(old('is_active', $fornecedor?->is_active ?? true) == false)>Inativo</option>
        </select>
    </div>
</div>
