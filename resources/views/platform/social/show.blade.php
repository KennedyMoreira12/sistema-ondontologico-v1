@extends('layouts.platform')

@section('title', $post->titulo)
@section('page-title', 'Redes sociais')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">{{ $post->titulo }}</h1>
            <p class="text-muted small mb-0">
                Criado em {{ $post->created_at?->format('d/m/Y H:i') }}
                · <span class="badge bg-{{ $post->statusBadge() }}">{{ $post->statusLabel() }}</span>
            </p>
        </div>
        <a href="{{ route('painel.social.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>

    @if ($post->error)
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ $post->error }}</div>
    @endif

    @if ($post->published_at)
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-1"></i>Publicado em {{ $post->published_at->format('d/m/Y H:i') }}.
        </div>
    @endif

    @unless ($setting?->page_access_token)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            As contas ainda não foram configuradas.
            <a href="{{ route('painel.social.configuracao') }}" class="alert-link">Configurar agora</a>.
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                @if ($post->image_url)
                    <img src="{{ $post->image_url }}" alt="{{ $post->titulo }}" class="card-img-top">
                @else
                    <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="height: 260px;">
                        Sem imagem
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0"><i class="bi bi-facebook me-1"></i>Facebook</h2>
                        @if ($post->publicaNoFacebook())
                            <span class="badge bg-{{ $post->facebook_status === 'publicado' ? 'success' : ($post->facebook_status === 'erro' ? 'danger' : 'secondary') }}">
                                {{ $post->facebook_status }}
                            </span>
                        @else
                            <span class="badge bg-light text-muted">não selecionado</span>
                        @endif
                    </div>

                    @if ($post->facebook_post_id)
                        <div class="small text-muted mb-2">ID: <code>{{ $post->facebook_post_id }}</code></div>
                    @endif

                    <p class="mb-0 small" style="white-space: pre-line;">{{ $post->legendaPara('facebook') ?: 'Sem legenda.' }}</p>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0"><i class="bi bi-instagram me-1"></i>Instagram</h2>
                        @if ($post->publicaNoInstagram())
                            <span class="badge bg-{{ $post->instagram_status === 'publicado' ? 'success' : ($post->instagram_status === 'erro' ? 'danger' : 'secondary') }}">
                                {{ $post->instagram_status }}
                            </span>
                        @else
                            <span class="badge bg-light text-muted">não selecionado</span>
                        @endif
                    </div>

                    @if ($post->instagram_media_id)
                        <div class="small text-muted mb-2">ID: <code>{{ $post->instagram_media_id }}</code></div>
                    @endif

                    <p class="mb-0 small" style="white-space: pre-line;">{{ $post->legendaPara('instagram') ?: 'Sem legenda.' }}</p>
                </div>
            </div>

            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('painel.social.publicar', $post) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>{{ $post->published_at ? 'Publicar de novo' : 'Publicar agora' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('painel.social.destroy', $post) }}"
                      onsubmit="return confirm('Excluir este post?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Excluir</button>
                </form>
            </div>
        </div>
    </div>
@endsection
