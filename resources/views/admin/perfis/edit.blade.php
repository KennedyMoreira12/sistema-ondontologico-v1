@extends('layouts.app')

@section('title', 'Editar perfil — ' . ($role->label ?? $role->name))

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.perfis.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">{{ $role->label ?? $role->name }}</h1>
            <p class="text-muted small mb-0">Marque as permissões deste perfil.</p>
        </div>
    </div>

    @if ($role->name === 'admin')
        <div class="alert alert-warning">
            <i class="bi bi-shield-check me-2"></i>Este perfil possui acesso total e pode não seguir a lista de permissões abaixo.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.perfis.update', $role) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-3" style="max-width: 640px;">
            <div class="card-body">
                <label for="label" class="form-label">Nome de exibição</label>
                <input type="text" id="label" name="label" value="{{ old('label', $role->label) }}" class="form-control" placeholder="{{ $role->name }}">
            </div>
        </div>

        @foreach ($permissions as $module => $items)
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex align-items-center gap-2">
                    <div class="form-check">
                        <input class="form-check-input check-all" type="checkbox" data-module="{{ $module }}">
                    </div>
                    <span class="fw-semibold text-capitalize">{{ $module }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach ($items as $permission)
                            <div class="col-md-4 col-lg-3">
                                <div class="form-check">
                                    <input
                                        class="form-check-input perm-check"
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission->id }}"
                                        id="perm-{{ $permission->id }}"
                                        data-module="{{ $module }}"
                                        @checked($role->permissions->contains('id', $permission->id))
                                    >
                                    <label class="form-check-label small" for="perm-{{ $permission->id }}">{{ $permission->action }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('admin.perfis.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar permissões</button>
        </div>
    </form>

    @push('scripts')
        <script>
            document.querySelectorAll('.check-all').forEach(function (checkAll) {
                checkAll.addEventListener('change', function () {
                    document.querySelectorAll('.perm-check[data-module="' + this.dataset.module + '"]')
                        .forEach(function (box) { box.checked = checkAll.checked; });
                });
            });
        </script>
    @endpush
@endsection