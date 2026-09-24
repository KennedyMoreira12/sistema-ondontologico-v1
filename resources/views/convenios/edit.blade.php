@extends('layouts.app')

@section('title', 'Editar operadora')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('convenios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Editar operadora</h1>
            <p class="text-muted small mb-0">{{ $operadora->nome }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('convenios.update', $operadora) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-shield-check me-2"></i>Dados da operadora</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" id="nome" name="nome" value="{{ old('nome', $operadora->nome) }}" class="form-control @error('nome') is-invalid @enderror" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label for="cnpj" class="form-label">CNPJ</label>
                        <input type="text" id="cnpj" name="cnpj" value="{{ old('cnpj', $operadora->cnpj) }}" class="form-control cnpj-mask @error('cnpj') is-invalid @enderror" placeholder="00.000.000/0000-00">
                        @error('cnpj')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $operadora->email) }}" class="form-control @error('email') is-invalid @enderror">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label">Telefone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $operadora->phone) }}" class="form-control phone-mask @error('phone') is-invalid @enderror">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="is_active" class="form-select">
                            <option value="1" @selected(old('is_active', $operadora->is_active ? '1' : '0') == '1')>Ativo</option>
                            <option value="0" @selected(old('is_active', $operadora->is_active ? '1' : '0') == '0')>Inativo</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="responsavel" class="form-label">Responsável</label>
                        <input type="text" id="responsavel" name="responsavel" value="{{ old('responsavel', $operadora->responsavel) }}" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('convenios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar alterações</button>
        </div>
    </form>
@endsection