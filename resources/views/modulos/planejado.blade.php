@extends('layouts.app')

@section('title', $titulo)

@section('page-title', $titulo)

@section('content')
    {{-- Tela padrão de módulo que ainda não está disponível para a clínica.
         A mensagem é a mesma em todos os módulos, para o usuário não entender
         que algo quebrou: o módulo existe, mas ainda não foi entregue. --}}
    <div class="row justify-content-center">
        <div class="col-12 col-md-9 col-lg-7">
            <div class="card shadow-sm text-center">
                <div class="card-body p-4 p-md-5">
                    <div class="display-4 text-muted"><i class="bi bi-tools"></i></div>

                    <h1 class="h3 mt-3">{{ $titulo }}</h1>

                    <p class="lead text-muted mb-2">Funcionalidade planejada — ainda não disponível para uso.</p>

                    @if ($descricao)
                        <p class="text-muted small">{{ $descricao }}</p>
                    @endif

                    <a href="{{ route('dashboard') }}" class="btn btn-primary mt-2">
                        <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
