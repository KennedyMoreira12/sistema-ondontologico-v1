@extends('layouts.app')

@section('title', 'Social')
@section('page-title', 'Social')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Social</h1>
            <p class="text-muted small mb-0">{{ $posts->total() }} publicação(ões) da clínica no Instagram e no Facebook.</p>
        </div>
        <div class="d-flex gap-2">
            @can('social.manage')
                <a href="{{ route('social.configuracao') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-gear me-1"></i>Configuração
                </a>
                <a href="{{ route('social.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Novo Post
                </a>
            @endcan
        </div>
    </div>

    @can('social.manage')
        @unless ($setting?->page_access_token)
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                As contas do Instagram/Facebook da clínica ainda não foram configuradas.
                <a href="{{ route('social.configuracao') }}" class="alert-link">Configurar agora</a>.
            </div>
        @endunless
    @endcan

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('social.index') }}" class="row g-2">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar por título ou tema">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Todos os status</option>
                        <option value="pendente" @selected(request('status') === 'pendente')>Pendente</option>
                        <option value="aprovado" @selected(request('status') === 'aprovado')>Aprovado</option>
                        <option value="rejeitado" @selected(request('status') === 'rejeitado')>Rejeitado</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        @forelse ($posts as $post)
            @php
                [$statusClass, $statusLabel] = match ($post->status) {
                    'aprovado' => ['bg-success', 'Aprovado'],
                    'rejeitado' => ['bg-danger', 'Rejeitado'],
                    default => ['bg-warning text-dark', 'Pendente'],
                };
            @endphp
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 shadow-sm">
                    @if ($post->image_url)
                        <img src="{{ $post->image_url }}" alt="{{ $post->titulo ?? 'Imagem do post' }}" class="card-img-top" style="height: 180px; object-fit: cover;">
                    @else
                        <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="height: 180px;">
                            <i class="bi bi-image" style="font-size: 2rem;"></i>
                        </div>
                    @endif

                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 class="h6 mb-0">{{ $post->titulo ?? 'Publicação #'.$post->id }}</h2>
                            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>

                        <div class="small text-muted mb-2">
                            @foreach (($post->platforms ?? []) as $plataforma)
                                <i class="bi bi-{{ $plataforma === 'facebook' ? 'facebook' : 'instagram' }} me-1"></i>
                            @endforeach
                            {{ $post->created_at?->format('d/m/Y H:i') }}
                            @if ($post->published_at)
                                · <span class="text-success"><i class="bi bi-check-circle me-1"></i>publicado</span>
                            @endif
                        </div>

                        <div class="mt-auto">
                            <a href="{{ route('social.show', $post) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center text-muted py-5">
                        Nenhuma publicação encontrada.
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    <div class="mt-3">{{ $posts->links() }}</div>
@endsection
