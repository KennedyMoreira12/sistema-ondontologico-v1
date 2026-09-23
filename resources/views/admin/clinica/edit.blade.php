@extends('layouts.app')

@section('title', 'Configurações da clínica')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Dados da clínica</h1>
            <p class="text-muted small mb-0">Informações exibidas em documentos e no atendimento.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.clinica.update', $clinica) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-3" style="max-width: 760px;">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-2"></i>Identificação</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="nome" class="form-label">Nome fantasia *</label>
                        <input type="text" id="nome" name="nome" value="{{ old('nome', $clinica->nome) }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="documento" class="form-label">CNPJ</label>
                        <input type="text" id="documento" name="documento" value="{{ old('documento', $clinica->documento) }}" class="form-control">
                    </div>
                    <div class="col-12">
                        <label for="razao_social" class="form-label">Razão social</label>
                        <input type="text" id="razao_social" name="razao_social" value="{{ old('razao_social', $clinica->razao_social) }}" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3" style="max-width: 760px;">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-telephone me-2"></i>Contato</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $clinica->email) }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label">Telefone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $clinica->phone) }}" class="form-control phone-mask">
                    </div>
                    <div class="col-md-4">
                        <label for="whatsapp" class="form-label">WhatsApp</label>
                        <input type="text" id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $clinica->whatsapp) }}" class="form-control phone-mask">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3" style="max-width: 760px;">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-2"></i>Endereço</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="cep" class="form-label">CEP</label>
                        <input type="text" id="cep" name="cep" value="{{ old('cep', $clinica->cep) }}" class="form-control cep-mask">
                    </div>
                    <div class="col-md-6">
                        <label for="endereco" class="form-label">Endereço</label>
                        <input type="text" id="endereco" name="endereco" value="{{ old('endereco', $clinica->endereco) }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" id="numero" name="numero" value="{{ old('numero', $clinica->numero) }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" id="complemento" name="complemento" value="{{ old('complemento', $clinica->complemento) }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" id="bairro" name="bairro" value="{{ old('bairro', $clinica->bairro) }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="cidade" class="form-label">Cidade</label>
                        <input type="text" id="cidade" name="cidade" value="{{ old('cidade', $clinica->cidade) }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="estado" class="form-label">UF</label>
                        <select id="estado" name="estado" class="form-select">
                            <option value="">—</option>
                            @foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                                <option value="{{ $uf }}" @selected(old('estado', $clinica->estado) === $uf)>{{ $uf }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar alterações</button>
            </div>
        </div>
    </form>
@endsection