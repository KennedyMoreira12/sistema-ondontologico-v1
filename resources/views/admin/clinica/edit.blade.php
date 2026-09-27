@extends('layouts.app')

@section('title', 'Configurações da clínica')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Dados da clínica</h1>
            <p class="text-muted small mb-0">Informações exibidas em documentos e no atendimento.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.clinica.update') }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-3" style="max-width: 760px;">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-2"></i>Identificação</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="legal_name" class="form-label">Razão social</label>
                        <input type="text" id="legal_name" name="legal_name" value="{{ old('legal_name', $perfil->legal_name) }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="document" class="form-label">CNPJ</label>
                        <input type="text" id="document" name="document" value="{{ old('document', $perfil->document) }}" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3" style="max-width: 760px;">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-telephone me-2"></i>Contato</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Telefone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $perfil->phone) }}" class="form-control phone-mask">
                    </div>
                    <div class="col-md-6">
                        <label for="whatsapp" class="form-label">WhatsApp</label>
                        <input type="text" id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $perfil->whatsapp) }}" class="form-control phone-mask">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3" style="max-width: 760px;">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-2"></i>Endereço</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="postal_code" class="form-label">CEP</label>
                        <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $perfil->postal_code) }}" class="form-control cep-mask">
                    </div>
                    <div class="col-md-6">
                        <label for="address" class="form-label">Endereço</label>
                        <input type="text" id="address" name="address" value="{{ old('address', $perfil->address) }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="number" class="form-label">Número</label>
                        <input type="text" id="number" name="number" value="{{ old('number', $perfil->number) }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="complement" class="form-label">Complemento</label>
                        <input type="text" id="complement" name="complement" value="{{ old('complement', $perfil->complement) }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="district" class="form-label">Bairro</label>
                        <input type="text" id="district" name="district" value="{{ old('district', $perfil->district) }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="city" class="form-label">Cidade</label>
                        <input type="text" id="city" name="city" value="{{ old('city', $perfil->city) }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="state" class="form-label">UF</label>
                        <select id="state" name="state" class="form-select">
                            <option value="">—</option>
                            @foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                                <option value="{{ $uf }}" @selected(old('state', $perfil->state) === $uf)>{{ $uf }}</option>
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
