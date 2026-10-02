@extends('layouts.platform')

@section('title', 'Novo plano')
@section('page-title', 'Novo plano')

@section('content')
    <div class="mb-4">
        <h1 class="h4 mb-0">Novo plano</h1>
        <p class="text-muted small mb-0">Recurso marcado entra no plano; o limite é opcional.</p>
    </div>

    <form method="POST" action="{{ route('painel.planos.store') }}">
        @csrf
        @include('platform.planos._form', ['plano' => null])
@endsection
