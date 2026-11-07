@extends('layouts.app')

@section('title', 'Configurações da clínica')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Dados da clínica</h1>
            <p class="text-muted small mb-0">Informações exibidas em documentos e no atendimento.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.clinica.update') }}" enctype="multipart/form-data">
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
                    <div class="col-md-4">
                        <label for="phone" class="form-label">Telefone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $perfil->phone) }}" class="form-control phone-mask">
                    </div>
                    <div class="col-md-4">
                        <label for="whatsapp" class="form-label">WhatsApp</label>
                        <input type="text" id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $perfil->whatsapp) }}" class="form-control phone-mask">
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $perfil->email) }}" class="form-control">
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
        </div>

        <div class="card shadow-sm mb-3" style="max-width: 760px;">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-palette me-2"></i>Marca</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="primary_color" class="form-label">Cor principal</label>
                        <input
                            type="color"
                            id="primary_color"
                            name="primary_color"
                            class="form-control form-control-color"
                            value="{{ old('primary_color', $clinica?->primary_color ?: '#0b7285') }}"
                        >
                        @error('primary_color')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">Cor do menu ativo, dos títulos e dos botões.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="secondary_color" class="form-label">Cor secundária</label>
                        <input
                            type="color"
                            id="secondary_color"
                            name="secondary_color"
                            class="form-control form-control-color"
                            value="{{ old('secondary_color', $clinica?->secondary_color ?: '#0ca678') }}"
                        >
                        @error('secondary_color')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">Acompanha a principal na borda de destaque.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="logo" class="form-label">Logo</label>
                        <input type="file" id="logo" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                        @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                        @if ($clinica?->logo_path)
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <img src="{{ clinic_logo_url() }}" alt="Logo atual da clínica" class="img-thumbnail" style="max-height: 44px;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="remover_logo" value="1" id="remover_logo" @checked(old('remover_logo'))>
                                    <label class="form-check-label" for="remover_logo">Remover</label>
                                </div>
                            </div>
                        @endif

                        <div class="form-text">PNG, JPG ou WEBP de até 2 MB.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3" style="max-width: 760px;">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-2"></i>Dados fiscais (emissão de nota)</div>
            <div class="card-body">
                <p class="text-muted small">
                    Usados na emissão de NFS-e. Sem inscrição municipal e código IBGE o provedor pode recusar a nota.
                </p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="municipal_registration" class="form-label">Inscrição municipal</label>
                        <input type="text" id="municipal_registration" name="municipal_registration"
                            value="{{ old('municipal_registration', $perfil->municipal_registration) }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="state_registration" class="form-label">Inscrição estadual</label>
                        <input type="text" id="state_registration" name="state_registration"
                            value="{{ old('state_registration', $perfil->state_registration) }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="cnae" class="form-label">CNAE</label>
                        <input type="text" id="cnae" name="cnae" value="{{ old('cnae', $perfil->cnae) }}" class="form-control" placeholder="0000-0/00">
                    </div>
                    <div class="col-md-4">
                        <label for="tax_regime" class="form-label">Regime tributário</label>
                        <select id="tax_regime" name="tax_regime" class="form-select">
                            <option value="">—</option>
                            @foreach (['simples' => 'Simples Nacional', 'simples_nacional' => 'Simples Nacional (ME/EPP)', 'mei' => 'MEI', 'lucro_presumido' => 'Lucro presumido', 'lucro_real' => 'Lucro real'] as $valor => $label)
                                <option value="{{ $valor }}" @selected(old('tax_regime', $perfil->tax_regime) === $valor)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="city_ibge_code" class="form-label">Código IBGE do município</label>
                        <input type="text" id="city_ibge_code" name="city_ibge_code" maxlength="7"
                            value="{{ old('city_ibge_code', $perfil->city_ibge_code) }}" class="form-control" placeholder="0000000">
                    </div>
                </div>
            </div>
        </div>

        <div style="max-width: 760px;">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar alterações</button>
        </div>
    </form>
@endsection
