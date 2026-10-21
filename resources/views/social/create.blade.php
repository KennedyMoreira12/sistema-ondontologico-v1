@extends('layouts.app')

@section('title', 'Novo Post')
@section('page-title', 'Social')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Novo Post</h1>
            <p class="text-muted small mb-0">Escreva as legendas e escolha em quais redes publicar.</p>
        </div>
        <a href="{{ route('social.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
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

    <form method="POST" action="{{ route('social.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Conteúdo</h2>

                <div class="row g-3">
                    <div class="col-12">
                        <label for="titulo" class="form-label">Título (controle interno) *</label>
                        <input id="titulo" name="titulo" type="text" maxlength="255"
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
                        <textarea id="legenda_facebook" name="legenda_facebook" rows="5" maxlength="5000"
                                  class="form-control @error('legenda_facebook') is-invalid @enderror">{{ old('legenda_facebook') }}</textarea>
                        @error('legenda_facebook')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="hashtags" class="form-label">Hashtags</label>
                        <input id="hashtags" name="hashtags" type="text" maxlength="1000"
                               class="form-control @error('hashtags') is-invalid @enderror"
                               value="{{ old('hashtags') }}" placeholder="#odonto #saude #sorriso">
                        @error('hashtags')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Separe por espaço ou vírgula.</div>
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
                        <div class="form-text">JPG, PNG ou WEBP, até 10 MB.</div>

                        <img id="preview-imagem" src="" alt="Pré-visualização" class="img-fluid rounded border mt-3 d-none">

                        <label for="image_url" class="form-label mt-3">Ou URL da imagem</label>
                        <input id="image_url" name="image_url" type="url" maxlength="1000"
                               class="form-control @error('image_url') is-invalid @enderror"
                               value="{{ old('image_url') }}" placeholder="https://…">
                        @error('image_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Apenas uma URL pública começando com <code>https://</code>; ignorada se você enviar um arquivo.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label d-block">Publicar em *</label>
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
            <a href="{{ route('social.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.getElementById('image')?.addEventListener('change', function (evento) {
            const arquivo = evento.target.files?.[0];
            const preview = document.getElementById('preview-imagem');

            if (! arquivo || ! preview) {
                return;
            }

            preview.src = URL.createObjectURL(arquivo);
            preview.classList.remove('d-none');
        });
    </script>
@endpush
