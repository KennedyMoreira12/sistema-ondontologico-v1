@extends('layouts.app')

@section('title', 'Permissões')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Permissões</h1>
            <p class="text-muted small mb-0">Catálogo de permissões do sistema, organizadas por módulo.</p>
        </div>
    </div>

    <div class="accordion" id="permissoesAccordion">
        @foreach ($permissions as $module => $items)
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#mod-{{ \Illuminate\Support\Str::slug($module) }}">
                        <span class="text-capitalize">{{ $module }}</span>
                        <span class="badge bg-primary ms-2">{{ $items->count() }}</span>
                    </button>
                </h2>
                <div id="mod-{{ \Illuminate\Support\Str::slug($module) }}" class="accordion-collapse collapse show" data-bs-parent="#permissoesAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            @foreach ($items as $permission)
                                <div class="col-md-4 col-lg-3">
                                    <code>{{ $permission->name }}</code>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection