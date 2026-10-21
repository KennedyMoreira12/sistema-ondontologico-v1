@extends('layouts.platform')

@section('title', 'Redes sociais · Configuração')
@section('page-title', 'Redes sociais')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Redes sociais</h1>
            <p class="text-muted small mb-0">Contas de Instagram e Facebook da LJ usadas para publicar os posts desta área.</p>
        </div>
        <a href="{{ route('painel.social.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar aos posts
        </a>
    </div>

    @if ($setting?->verified_at)
        <div class="alert alert-success">
            <i class="bi bi-patch-check me-1"></i>
            Conexão verificada em {{ $setting->verified_at->format('d/m/Y H:i') }}.
            @if ($setting->facebook_page_name)
                Página: <strong>{{ $setting->facebook_page_name }}</strong>.
            @endif
            @if ($setting->instagram_username)
                Instagram: <strong>&#64;{{ $setting->instagram_username }}</strong>.
            @endif
        </div>
    @elseif ($setting?->last_error)
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ $setting->last_error }}
        </div>
    @endif

    <form method="POST" action="{{ route('painel.social.configuracao.update') }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Contas da Meta</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="facebook_page_id" class="form-label">ID da Página do Facebook</label>
                        <input id="facebook_page_id" name="facebook_page_id" type="text"
                               class="form-control @error('facebook_page_id') is-invalid @enderror"
                               value="{{ old('facebook_page_id', $setting?->facebook_page_id) }}"
                               placeholder="Ex.: 123456789012345">
                        @error('facebook_page_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="instagram_business_id" class="form-label">ID da conta Instagram Business</label>
                        <input id="instagram_business_id" name="instagram_business_id" type="text"
                               class="form-control @error('instagram_business_id') is-invalid @enderror"
                               value="{{ old('instagram_business_id', $setting?->instagram_business_id) }}"
                               placeholder="Ex.: 17841400000000000">
                        @error('instagram_business_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Pode ficar em branco se você só publicar no Facebook.</div>
                    </div>

                    <div class="col-12">
                        <label for="page_access_token" class="form-label">Page Access Token (longa duração)</label>
                        <input id="page_access_token" name="page_access_token" type="password"
                               class="form-control @error('page_access_token') is-invalid @enderror"
                               value="" autocomplete="new-password"
                               placeholder="{{ $setting?->page_access_token ? 'salvo — deixe em branco para manter' : 'Cole o token aqui' }}">
                        @error('page_access_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">O token fica cifrado no banco e nunca é exibido de volta. Deixe em branco para manter o atual.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="graph_version" class="form-label">Versão da Graph API</label>
                        <input id="graph_version" name="graph_version" type="text"
                               class="form-control @error('graph_version') is-invalid @enderror"
                               value="{{ old('graph_version', $setting?->graph_version ?? config('services.social_publish.graph_version')) }}"
                               placeholder="v23.0">
                        @error('graph_version')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar configuração</button>
            <a href="{{ route('painel.social.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    @if ($setting?->page_access_token)
        <form method="POST" action="{{ route('painel.social.configuracao.verificar') }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-plug me-1"></i>Verificar conexão
            </button>
            <span class="text-muted small ms-2">Lê o nome da Página e o Instagram vinculado. Não publica nada.</span>
        </form>
    @endif
@endsection
