@extends('layouts.app')

@section('title', 'Perfis de acesso')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Perfis de acesso</h1>
            <p class="text-muted small mb-0">Agrupam permissões e definem o que cada usuário pode fazer.</p>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($roles as $role)
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-1">{{ $role->label ?? $role->name }}</h2>
                        <div class="text-muted small mb-3">
                            {{ $role->permissions_count }} permissões • {{ $role->user_roles_count }} usuário(s)
                        </div>
                        @if ($role->name === 'admin')
                            <span class="badge bg-warning text-dark">Acesso total</span>
                        @endif
                    </div>
                    @can('admin.update')
                        <div class="card-footer bg-white">
                            <a href="{{ route('admin.perfis.edit', $role) }}" class="btn btn-sm btn-outline-primary w-100">
                                <i class="bi bi-gear me-1"></i>Gerenciar permissões
                            </a>
                        </div>
                    @endcan
                </div>
            </div>
        @endforeach
    </div>
@endsection