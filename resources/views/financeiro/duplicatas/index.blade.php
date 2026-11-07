@extends('layouts.app')

@section('title', 'Financeiro — Nomes parecidos')

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-0">Nomes parecidos</h1>
        <p class="text-muted small mb-0">Conferência de cadastros com nomes iguais ou semelhantes. Nada é fundido automaticamente.</p>
    </div>

    @include('financeiro.partials.nav')

    <div class="alert alert-info d-flex gap-2">
        <i class="bi bi-info-circle"></i>
        <div class="small">
            Dois cadastros com o mesmo nome podem ser duas pessoas diferentes. A fusão mexe no histórico clínico e
            financeiro, então ela não é feita pelo sistema: esta tela só mostra os grupos para conferência manual.
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-2"></i>Nomes exatamente iguais ({{ count($exatos) }} grupos)</div>
        <div class="card-body">
            @forelse ($exatos as $grupo)
                <div class="border rounded p-3 mb-2">
                    <div class="fw-semibold mb-2">{{ $grupo['chave'] }}</div>
                    <ul class="mb-0 small">
                        @foreach ($grupo['pacientes'] as $paciente)
                            <li>#{{ $paciente->id }} — {{ $paciente->nome }} <span class="text-muted">({{ $paciente->status }})</span></li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="text-muted mb-0">Nenhum grupo com nome exatamente igual.</p>
            @endforelse
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-shuffle me-2"></i>Nomes semelhantes ({{ count($semelhantes) }} pares)</div>
        <div class="card-body">
            @forelse ($semelhantes as $par)
                <div class="d-flex justify-content-between border-bottom py-2 small">
                    <span>#{{ $par['a']->id }} {{ $par['a']->nome }}</span>
                    <span class="text-muted mx-2">×</span>
                    <span>#{{ $par['b']->id }} {{ $par['b']->nome }}</span>
                    <span class="badge text-bg-secondary">{{ number_format($par['score'] * 100, 1, ',', '.') }}%</span>
                </div>
            @empty
                <p class="text-muted mb-0">Nenhum par semelhante acima do limite de segurança.</p>
            @endforelse
        </div>
    </div>
@endsection
