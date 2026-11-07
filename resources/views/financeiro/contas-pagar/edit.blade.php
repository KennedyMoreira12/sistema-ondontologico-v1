@extends('layouts.app')

@section('title', 'Editar conta a pagar')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('financeiro.contas-pagar.show', $conta) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Editar conta a pagar</h1>
            <p class="text-muted small mb-0">{{ $conta->nome_conta }}</p>
        </div>
    </div>

    @include('financeiro.partials.nav')

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('financeiro.contas-pagar.update', $conta) }}">
        @csrf
        @method('PUT')
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil me-2"></i>Dados da conta</div>
            <div class="card-body">
                @include('financeiro.contas-pagar._form', ['conta' => $conta])
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('financeiro.contas-pagar.show', $conta) }}" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar alterações</button>
        </div>
    </form>
@endsection
