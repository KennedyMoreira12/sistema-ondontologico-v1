@extends('layouts.app')

@section('title', 'Novo paciente')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Novo paciente</h1>
            <p class="text-muted small mb-0">Cadastro de paciente novo.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('pacientes.store') }}">
        @csrf

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2"></i>Dados pessoais</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="nome" class="form-label">Nome completo *</label>
                        <input type="text" id="nome" name="nome" value="{{ old('nome') }}" class="form-control @error('nome') is-invalid @enderror" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="sexo" class="form-label">Sexo</label>
                        <select id="sexo" name="sexo" class="form-select">
                            <option value="">—</option>
                            <option value="masculino" @selected(old('sexo') === 'masculino')>Masculino</option>
                            <option value="feminino" @selected(old('sexo') === 'feminino')>Feminino</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" id="cpf" name="cpf" value="{{ old('cpf') }}" class="form-control cpf-mask @error('cpf') is-invalid @enderror" placeholder="000.000.000-00">
                        @error('cpf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="rg" class="form-label">RG</label>
                        <input type="text" id="rg" name="rg" value="{{ old('rg') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="nascimento" class="form-label">Data de nascimento</label>
                        <input type="date" id="nascimento" name="nascimento" value="{{ old('nascimento') }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="phone" class="form-label">Telefone fixo</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}" class="form-control phone-mask">
                    </div>
                    <div class="col-md-3">
                        <label for="celular" class="form-label">Celular</label>
                        <input type="text" id="celular" name="celular" value="{{ old('celular') }}" class="form-control phone-mask">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-2"></i>Endereço</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="cep" class="form-label">CEP</label>
                        <input type="text" id="cep" name="cep" value="{{ old('cep') }}" class="form-control cep-mask">
                    </div>
                    <div class="col-md-6">
                        <label for="endereco" class="form-label">Endereço</label>
                        <input type="text" id="endereco" name="endereco" value="{{ old('endereco') }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" id="numero" name="numero" value="{{ old('numero') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" id="complemento" name="complemento" value="{{ old('complemento') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" id="bairro" name="bairro" value="{{ old('bairro') }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="cidade" class="form-label">Cidade</label>
                        <input type="text" id="cidade" name="cidade" value="{{ old('cidade') }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="estado" class="form-label">UF</label>
                        <select id="estado" name="estado" class="form-select">
                            <option value="">—</option>
                            @foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                                <option value="{{ $uf }}" @selected(old('estado') === $uf)>{{ $uf }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>Outras informações</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="responsavel" class="form-label">Responsável legal</label>
                        <input type="text" id="responsavel" name="responsavel" value="{{ old('responsavel') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="ativo" @selected(old('status', 'ativo') === 'ativo')>Ativo</option>
                            <option value="inativo" @selected(old('status') === 'inativo')>Inativo</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3" class="form-control">{{ old('observacoes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar paciente</button>
        </div>
    </form>
@endsection