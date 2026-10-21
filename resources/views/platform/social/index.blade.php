@extends('layouts.platform')

@section('title', 'Redes sociais')
@section('page-title', 'Redes sociais')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Redes sociais</h1>
            <p class="text-muted small mb-0">Posts da LJ publicados no Instagram e no Facebook.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('painel.social.configuracao') }}" class="btn btn-outline-secondary">
                <i class="bi bi-gear me-1"></i>Configuração
            </a>
            <a href="{{ route('painel.social.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Novo post
            </a>
        </div>
    </div>

    @unless ($setting?->page_access_token)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            As contas do Instagram/Facebook ainda não foram configuradas.
            <a href="{{ route('painel.social.configuracao') }}" class="alert-link">Configurar agora</a>.
        </div>
    @endunless

    <div class="row g-3">
        @forelse ($posts as $post)
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 shadow-sm">
                    @if ($post->image_url)
                        <img src="{{ $post->image_url }}" alt="{{ $post->titulo }}" class="card-img-top" style="height: 180px; object-fit: cover;">
                    @else
                        <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="height: 180px;">
                            <i class="bi bi-image" style="font-size: 2rem;"></i>
                        </div>
                    @endif

                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 class="h6 mb-0">{{ $post->titulo }}</h2>
                            <span class="badge bg-{{ $post->statusBadge() }}">{{ $post->statusLabel() }}</span>
                        </div>

                        <div class="small text-muted mb-2">
                            @if ($post->publicaNoFacebook())<i class="bi bi-facebook me-1"></i>@endif
                            @if ($post->publicaNoInstagram())<i class="bi bi-instagram me-1"></i>@endif
                            {{ $post->created_at?->format('d/m/Y H:i') }}
                        </div>

                        <div class="mt-auto">
                            <a href="{{ route('painel.social.show', $post) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center text-muted py-5">
                        Nenhum post ainda. Clique em <strong>Novo post</strong> para começar.
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    <div class="mt-3">{{ $posts->links() }}</div>
@endsection
