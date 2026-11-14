@extends('layouts.app')

@section('title', 'Fiscal - NFS-e')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">Fiscal - NFS-e</h1>
            <p class="text-muted small mb-0">Configuração de emissão de Nota Fiscal de Serviço eletrônica (SendNF) da clínica.</p>
        </div>
        <a href="{{ route('fiscal.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Ir para Notas fiscais
        </a>
    </div>

    @include('admin.fiscal.partials.configuracao')
@endsection
