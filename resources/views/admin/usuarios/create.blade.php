@extends('layouts.app')

@section('title', 'Novo usuário')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Novo usuário</h1>
            <p class="text-muted small mb-0">Cria um acesso para esta clínica.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.usuarios.store') }}">
        @csrf

        <div class="card shadow-sm mb-3" style="max-width: 640px;">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="name" class="form-label">Nome *</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="email" class="form-label">E-mail *</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                        <div class="form-text">O e-mail é único em todo o sistema: é com ele que o usuário entra e o sistema descobre a clínica.</div>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" id="cpf" name="cpf" value="{{ old('cpf') }}" class="form-control cpf-mask">
                        @error('cpf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Telefone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}" class="form-control phone-mask">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="role_ids" class="form-label">Perfis de acesso *</label>
                        <select id="role_ids" name="role_ids[]" multiple size="5" class="form-select @error('role_ids') is-invalid @enderror" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected(in_array($role->id, old('role_ids', [])))>{{ $role->label ?? $role->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Os perfis são desta clínica. Segure Ctrl (ou Cmd) para escolher mais de um.</div>
                        @error('role_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @error('role_ids.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Senha *</label>
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Confirmar senha *</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="is_active">Usuário ativo</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </form>
@endsection
