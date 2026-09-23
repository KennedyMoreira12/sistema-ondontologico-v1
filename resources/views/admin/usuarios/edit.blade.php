@extends('layouts.app')

@section('title', 'Editar usuário')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Editar — {{ $user->name }}</h1>
            <p class="text-muted small mb-0">Atualize os dados de acesso do usuário.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.usuarios.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-3" style="max-width: 640px;">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="name" class="form-label">Nome *</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="email" class="form-label">E-mail *</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" id="cpf" name="cpf" value="{{ old('cpf', $user->cpf) }}" class="form-control cpf-mask">
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label">Telefone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control phone-mask">
                    </div>
                    <div class="col-md-4">
                        <label for="role_id" class="form-label">Perfil *</label>
                        <select id="role_id" name="role_id" class="form-select @error('role_id') is-invalid @enderror" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->label ?? $role->name }}</option>
                            @endforeach
                        </select>
                        @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="clinica_id" class="form-label">Clínica</label>
                        <select id="clinica_id" name="clinica_id" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach ($clinicas as $clinica)
                                <option value="{{ $clinica->id }}" @selected(old('clinica_id', $user->clinica_id) == $clinica->id)>{{ $clinica->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Nova senha <span class="text-muted">(opcional)</span></label>
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked($user->is_active)>
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