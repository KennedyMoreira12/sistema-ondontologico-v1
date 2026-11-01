@extends('layouts.platform')

@section('title', 'Novo post · Redes sociais')
@section('page-title', 'Redes sociais')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Novo post</h1>
            <p class="text-muted small mb-0">Escreva as legendas e escolha em quais redes publicar.</p>
        </div>
        <a href="{{ route('painel.social.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>

    <form method="POST" action="{{ route('painel.social.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Conteúdo</h2>

                <div class="row g-3">
                    <div class="col-12">
                        <label for="titulo" class="form-label">Título (controle interno)</label>
                        <input id="titulo" name="titulo" type="text"
                               class="form-control @error('titulo') is-invalid @enderror"
                               value="{{ old('titulo') }}" required>
                        @error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="legenda_instagram" class="form-label">Legenda do Instagram</label>
                        <textarea id="legenda_instagram" name="legenda_instagram" rows="5" maxlength="2200"
                                  class="form-control @error('legenda_instagram') is-invalid @enderror">{{ old('legenda_instagram') }}</textarea>
                        @error('legenda_instagram')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="legenda_facebook" class="form-label">Legenda do Facebook</label>
                        <textarea id="legenda_facebook" name="legenda_facebook" rows="5"
                                  class="form-control @error('legenda_facebook') is-invalid @enderror">{{ old('legenda_facebook') }}</textarea>
                        @error('legenda_facebook')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="hashtags" class="form-label">Hashtags</label>
                        <input id="hashtags" name="hashtags" type="text"
                               class="form-control @error('hashtags') is-invalid @enderror"
                               value="{{ old('hashtags') }}" placeholder="#odonto #saude #sorriso">
                        @error('hashtags')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Separe por espaço. São acrescentadas no fim da legenda.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Imagem e redes</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="image" class="form-label">Imagem</label>
                        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                               class="form-control @error('image') is-invalid @enderror">
                        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Obrigatória para publicar no Instagram. JPG, PNG ou WEBP, até 8 MB.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label d-block">Publicar em</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="platforms[]" value="instagram"
                                   id="plat_instagram" @checked(in_array('instagram', old('platforms', ['instagram']), true))>
                            <label class="form-check-label" for="plat_instagram"><i class="bi bi-instagram me-1"></i>Instagram</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="platforms[]" value="facebook"
                                   id="plat_facebook" @checked(in_array('facebook', old('platforms', ['instagram']), true))>
                            <label class="form-check-label" for="plat_facebook"><i class="bi bi-facebook me-1"></i>Facebook</label>
                        </div>
                        @error('platforms')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @error('platforms.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Criar post</button>
            <a href="{{ route('painel.social.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
