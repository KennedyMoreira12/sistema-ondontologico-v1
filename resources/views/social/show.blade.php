@extends('layouts.app')

@section('title', $socialPost->titulo ?? 'Publicação social')

@php
    $statusLabels = [
        'pendente' => ['bg-warning text-dark', 'Pendente'],
        'aprovado' => ['bg-success', 'Aprovado'],
        'rejeitado' => ['bg-danger', 'Rejeitado'],
    ];
    [$statusClass, $statusLabel] = $statusLabels[$socialPost->status] ?? ['bg-secondary', ucfirst((string) $socialPost->status)];
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('social.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">{{ $socialPost->titulo ?? 'Publicação #'.$socialPost->id }}</h1>
                <div class="text-muted small">
                    #{{ $socialPost->id }} • <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
            </div>
        </div>
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

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-image me-2"></i>Imagem</div>
                <div class="card-body">
                    @if ($socialPost->image_url)
                        <img src="{{ $socialPost->image_url }}" alt="{{ $socialPost->titulo ?? 'Imagem do post' }}" class="img-fluid rounded border">
                        <div class="small text-muted mt-2">{{ $socialPost->image_url }}</div>
                    @else
                        <p class="text-muted mb-0">Imagem ainda não gerada.</p>
                    @endif

                    @if ($socialPost->payload['image_generation']['error'] ?? null)
                        <div class="alert alert-warning py-2 mb-0 mt-3">
                            Não foi possível gerar a imagem: {{ $socialPost->payload['image_generation']['error'] }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-file-text me-2"></i>Conteúdo</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Tema</span>
                            <strong>{{ $socialPost->tema ?? '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Objetivo</span>
                            <strong>{{ $socialPost->objetivo ?? '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Público</span>
                            <strong>{{ $socialPost->publico ?? '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Tipo</span>
                            <strong>{{ $socialPost->tipo ?? '—' }}</strong>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block small">Legenda — Instagram</span>
                            <p class="mb-0" style="white-space: pre-line">{{ $socialPost->legenda_instagram ?? '—' }}</p>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block small">Legenda — Facebook</span>
                            <p class="mb-0" style="white-space: pre-line">{{ $socialPost->legenda_facebook ?? '—' }}</p>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block small">Chamada para ação</span>
                            <p class="mb-0">{{ $socialPost->cta ?? '—' }}</p>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block small">Hashtags</span>
                            <div>
                                @forelse ($socialPost->hashtags ?? [] as $hashtag)
                                    <span class="badge bg-secondary">{{ $hashtag }}</span>
                                @empty
                                    <span>—</span>
                                @endforelse
                            </div>
                        </div>
                        @if ($socialPost->observacoes)
                            <div class="col-12">
                                <span class="text-muted d-block small">Observações</span>
                                <p class="mb-0">{{ $socialPost->observacoes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>Informações</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Status</span>
                            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Plataformas</span>
                            <div>
                                @forelse (($socialPost->platforms ?? []) as $plataforma)
                                    <span class="badge bg-secondary text-uppercase">{{ $plataforma }}</span>
                                @empty
                                    <span>—</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Agendado para</span>
                            <strong>{{ $socialPost->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Criado em</span>
                            <strong>{{ $socialPost->created_at?->format('d/m/Y H:i') ?? '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Aprovado em</span>
                            <strong>{{ $socialPost->approved_at?->format('d/m/Y H:i') ?? '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block small">Publicado em</span>
                            <strong>{{ $socialPost->published_at?->format('d/m/Y H:i') ?? '—' }}</strong>
                        </div>
                        @if ($socialPost->status === 'rejeitado' && ($socialPost->payload['rejection_reason'] ?? null))
                            <div class="col-12">
                                <span class="text-muted d-block small">Motivo da rejeição</span>
                                <div class="alert alert-warning py-2 mb-0">{{ $socialPost->payload['rejection_reason'] }}</div>
                            </div>
                        @endif
                        @if ($socialPost->error)
                            <div class="col-12">
                                <span class="text-muted d-block small">Erro de publicação</span>
                                <div class="alert alert-danger py-2 mb-0">{{ $socialPost->error }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($socialPost->status === 'pendente')
                @can('social.manage')
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-check2-circle me-2"></i>Revisão</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('social.approve', $socialPost) }}" onsubmit="return confirm('Aprovar esta publicação?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-success w-100 mb-3"><i class="bi bi-check-lg me-1"></i>Aprovar</button>
                            </form>

                            <form method="POST" action="{{ route('social.reject', $socialPost) }}">
                                @csrf
                                @method('PATCH')

                                <label for="motivo" class="form-label">Motivo da rejeição *</label>
                                <textarea id="motivo" name="motivo" rows="3" class="form-control @error('motivo') is-invalid @enderror" maxlength="500" required placeholder="Explique por que este conteúdo foi rejeitado">{{ old('motivo') }}</textarea>
                                @error('motivo')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                <button type="submit" class="btn btn-danger w-100 mt-3"><i class="bi bi-x-lg me-1"></i>Rejeitar</button>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif
        </div>
    </div>
@endsection
