@extends('layouts.app')

@section('title', $socialPost->titulo ?? 'Publicação social')
@section('page-title', 'Social')

@php
    [$statusClass, $statusLabel] = match ($socialPost->status) {
        'aprovado' => ['bg-success', 'Aprovado'],
        'rejeitado' => ['bg-danger', 'Rejeitado'],
        default => ['bg-warning text-dark', 'Pendente'],
    };
    $plataformas = $socialPost->platforms ?? [];

    $statusRede = function (?string $valor): array {
        return match ($valor) {
            'publicado' => ['bg-success', 'Publicado'],
            'erro' => ['bg-danger', 'Erro'],
            'ignorado' => ['bg-light text-muted', 'Ignorado'],
            default => ['bg-secondary', 'Pendente'],
        };
    };
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">{{ $socialPost->titulo ?? 'Publicação #'.$socialPost->id }}</h1>
            <p class="text-muted small mb-0">
                Criado em {{ $socialPost->created_at?->format('d/m/Y H:i') }}
                · <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                @if ($socialPost->published_at)
                    · <span class="text-success"><i class="bi bi-check-circle me-1"></i>Publicado em {{ $socialPost->published_at->format('d/m/Y H:i') }}</span>
                @endif
            </p>
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

    @if ($socialPost->status === 'rejeitado' && ($socialPost->payload['rejection_reason'] ?? null))
        <div class="alert alert-warning">
            <i class="bi bi-x-circle me-1"></i>{{ $socialPost->payload['rejection_reason'] }}
        </div>
    @endif

    @if ($socialPost->error)
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ $socialPost->error }}</div>
    @endif

    @unless ($setting?->page_access_token)
        @can('social.manage')
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                As contas do Instagram/Facebook da clínica ainda não foram configuradas.
                <a href="{{ route('social.configuracao') }}" class="alert-link">Configurar agora</a>.
            </div>
        @endcan
    @endunless

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                @if ($socialPost->image_url)
                    <img src="{{ $socialPost->image_url }}" alt="{{ $socialPost->titulo ?? 'Imagem do post' }}" class="card-img-top">
                @else
                    <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="height: 260px;">
                        Sem imagem
                    </div>
                @endif

                @can('social.manage')
                    <div class="card-body">
                        <form method="POST" action="{{ route('social.image', $socialPost) }}" enctype="multipart/form-data">
                            @csrf
                            <label for="image" class="form-label">{{ $socialPost->image_url ? 'Substituir imagem' : 'Enviar imagem' }}</label>
                            <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror" required>
                            @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">JPG, PNG ou WebP, até 10 MB.</div>
                            <img id="preview-imagem" src="" alt="Pré-visualização" class="img-fluid rounded border mt-3 d-none">
                            <button type="submit" class="btn btn-outline-primary mt-3">
                                <i class="bi bi-upload me-1"></i>{{ $socialPost->image_url ? 'Substituir imagem' : 'Enviar imagem' }}
                            </button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-lg-7">
            @php [$facebookClass, $facebookLabel] = $statusRede($socialPost->facebook_status); @endphp
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0"><i class="bi bi-facebook me-1"></i>Facebook</h2>
                        @if (in_array('facebook', $plataformas, true))
                            <span class="badge {{ $facebookClass }}">{{ $facebookLabel }}</span>
                        @else
                            <span class="badge bg-light text-muted">não selecionado</span>
                        @endif
                    </div>

                    @if ($socialPost->facebook_post_id)
                        <div class="small text-muted mb-2">ID: <code>{{ $socialPost->facebook_post_id }}</code></div>
                    @endif

                    <p class="mb-0 small" style="white-space: pre-line;">{{ $socialPost->legendaPara('facebook') ?: 'Sem legenda.' }}</p>
                </div>
            </div>

            @php [$instagramClass, $instagramLabel] = $statusRede($socialPost->instagram_status); @endphp
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0"><i class="bi bi-instagram me-1"></i>Instagram</h2>
                        @if (in_array('instagram', $plataformas, true))
                            <span class="badge {{ $instagramClass }}">{{ $instagramLabel }}</span>
                        @else
                            <span class="badge bg-light text-muted">não selecionado</span>
                        @endif
                    </div>

                    @if ($socialPost->instagram_media_id)
                        <div class="small text-muted mb-2">ID: <code>{{ $socialPost->instagram_media_id }}</code></div>
                    @endif

                    <p class="mb-0 small" style="white-space: pre-line;">{{ $socialPost->legendaPara('instagram') ?: 'Sem legenda.' }}</p>
                </div>
            </div>

            @unless (empty($socialPost->hashtags))
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="h6 mb-2">Hashtags</h2>
                        @foreach ($socialPost->hashtags as $hashtag)
                            <span class="badge bg-secondary me-1">{{ $hashtag }}</span>
                        @endforeach
                    </div>
                </div>
            @endunless

            @if (! empty($plataformas))
                @can('social.manage')
                    <div class="card shadow-sm mb-3">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h2 class="h6 mb-0">Publicação</h2>
                                <p class="text-muted small mb-0">Envia o post agora para as redes selecionadas, pelas contas configuradas da clínica.</p>
                            </div>
                            <form method="POST" action="{{ route('social.publicar', $socialPost) }}"
                                  onsubmit="return confirm('Publicar agora nas redes selecionadas?')">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i>{{ $socialPost->published_at ? 'Publicar de novo' : 'Publicar agora' }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif

            @if ($socialPost->status === 'pendente')
                @can('social.manage')
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Revisão</h2>

                            <form method="POST" action="{{ route('social.approve', $socialPost) }}" onsubmit="return confirm('Aprovar esta publicação?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-success w-100 mb-3"><i class="bi bi-check-lg me-1"></i>Aprovar</button>
                            </form>

                            <form method="POST" action="{{ route('social.reject', $socialPost) }}">
                                @csrf
                                @method('PATCH')

                                <label for="motivo" class="form-label">Motivo da rejeição *</label>
                                <textarea id="motivo" name="motivo" rows="3" class="form-control @error('motivo') is-invalid @enderror" maxlength="500" required>{{ old('motivo') }}</textarea>
                                @error('motivo')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                <button type="submit" class="btn btn-danger w-100 mt-3"><i class="bi bi-x-lg me-1"></i>Rejeitar</button>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif

            @if ($socialPost->status === 'aprovado')
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle me-1"></i>Aprovado em {{ $socialPost->approved_at?->format('d/m/Y H:i') }}.
                </div>
            @endif
        </div>
    </div>
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
