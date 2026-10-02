@extends('layouts.platform')

@section('title', 'Editar plano')
@section('page-title', 'Editar plano')

@section('content')
    <div class="mb-4">
        <h1 class="h4 mb-0">Editar plano</h1>
        <p class="text-muted small mb-0">{{ $plano->name }}</p>
    </div>

    <form method="POST" action="{{ route('painel.planos.update', $plano) }}">
        @csrf
        @method('PUT')
        @include('platform.planos._form')
    </form>
@endsection
