@extends('layouts.platform')

@section('title', 'Nova clínica')
@section('page-title', 'Nova clínica')

@section('content')
    <div class="mb-4">
        <h1 class="h4 mb-1">Nova clínica</h1>
        <p class="text-muted small mb-0">
            A criação monta o banco próprio da clínica, semeia papéis e permissões, cria o administrador
            e abre a assinatura no plano escolhido.
        </p>
    </div>

    <form method="POST" action="{{ route('painel.clinicas.store') }}">
        @csrf

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Dados da clínica</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nome</label>
                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autofocus>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="legal_name" class="form-label">Razão social</label>
                        <input id="legal_name" type="text" class="form-control" name="legal_name" value="{{ old('legal_name') }}">
                    </div>
                    <div class="col-md-4">
                        <label for="document" class="form-label">CNPJ</label>
                        <input id="document" type="text" class="form-control" name="document" value="{{ old('document') }}">
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">E-mail de contato</label>
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label">Telefone</label>
                        <input id="phone" type="text" class="form-control" name="phone" value="{{ old('phone') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">WhatsApp</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="evolution_instance" class="form-label">Instância da Evolution</label>
                        <input
                            id="evolution_instance"
                            type="text"
                            class="form-control @error('evolution_instance') is-invalid @enderror"
                            name="evolution_instance"
                            value="{{ old('evolution_instance') }}"
                            placeholder="clinica-malheiro"
                            pattern="[a-z0-9][a-z0-9._\-]*"
                        >
                        @error('evolution_instance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">
                            Só minúsculas, números, ponto, hífen ou underscore. É o nome com que o
                            webhook identifica esta clínica — se ficar vazio, as mensagens chegam sem
                            dona e são descartadas sem erro visível.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Administrador da clínica</h2>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="admin_name" class="form-label">Nome</label>
                        <input id="admin_name" type="text" class="form-control @error('admin_name') is-invalid @enderror" name="admin_name" value="{{ old('admin_name') }}" required>
                        @error('admin_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="admin_email" class="form-label">E-mail de acesso</label>
                        <input id="admin_email" type="email" class="form-control @error('admin_email') is-invalid @enderror" name="admin_email" value="{{ old('admin_email') }}" required>
                        @error('admin_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="password" class="form-label">Senha</label>
                        <input id="password" type="text" class="form-control @error('password') is-invalid @enderror" name="password" value="{{ old('password') }}" placeholder="gerada automaticamente">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Assinatura</h2>

                <label for="plan_id" class="form-label">Plano</label>
                <select id="plan_id" class="form-select @error('plan_id') is-invalid @enderror" name="plan_id" required>
                    <option value="">Selecione...</option>
                    @foreach ($planos as $plano)
                        <option value="{{ $plano->id }}" @selected((int) old('plan_id') === $plano->id)>
                            {{ $plano->name }} — {{ $plano->monthlyPrice() }}/mês
                            ({{ $plano->trial_days > 0 ? $plano->trial_days.' dias de avaliação' : 'sem avaliação' }})
                        </option>
                    @endforeach
                </select>
                @error('plan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Criar clínica
            </button>
            <a href="{{ route('painel.clinicas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
