@extends('layouts.platform')

@section('title', 'Planos')
@section('page-title', 'Planos')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Planos</h1>
            <p class="text-muted small mb-0">O que cada plano inclui é um dado comercial e muda sem deploy.</p>
        </div>
        @if (auth()->user()->isSuperAdmin())
            <a href="{{ route('painel.planos.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Novo plano
            </a>
        @endif
    </div>

    <div class="row g-3">
        @forelse ($planos as $plano)
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 shadow-sm {{ $plano->is_featured ? 'border-primary' : '' }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 class="h5 mb-0">{{ $plano->name }}</h2>
                            @if ($plano->is_featured)
                                <span class="badge bg-primary">destaque</span>
                            @endif
                        </div>

                        <div class="h4 mb-1">{{ $plano->monthlyPrice() }}<span class="fs-6 text-muted">/mês</span></div>

                        @if ($plano->trial_days > 0)
                            <div class="text-muted small mb-2">{{ $plano->trial_days }} dias de avaliação</div>
                        @endif

                        <p class="text-muted small">{{ $plano->description }}</p>

                        <ul class="list-unstyled small mb-3">
                            @forelse ($plano->features as $recurso)
                                <li class="py-1 border-bottom">
                                    <i class="bi bi-check-lg text-success me-1"></i>
                                    {{ config('tenancy.features.'.$recurso->feature, $recurso->feature) }}
                                    @if ($recurso->limits)
                                        <span class="text-muted">
                                            ({{ collect($recurso->limits)->map(fn ($v, $k) => $k.'='.$v)->join(', ') }})
                                        </span>
                                    @endif
                                </li>
                            @empty
                                <li class="text-muted">Sem recursos habilitados.</li>
                            @endforelse
                        </ul>

                        <div class="mt-auto d-flex gap-2">
                            @if (auth()->user()->isSuperAdmin())
                                <a href="{{ route('painel.planos.edit', $plano) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                            @endif
                            <span class="text-muted small">{{ $plano->subscriptions_count }} assinatura(s)</span>
                            @unless ($plano->is_active)
                                <span class="badge bg-secondary">inativo</span>
                            @endunless
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center text-muted py-5">
                        Nenhum plano cadastrado. Rode <code>php artisan db:seed --class=PlanSeeder</code>.
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection
