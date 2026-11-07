@extends('layouts.platform')

@section('title', 'Editar clínica')
@section('page-title', 'Editar clínica')

@section('content')
    <div class="mb-4">
        <h1 class="h4 mb-0">Editar clínica</h1>
        <p class="text-muted small mb-0">{{ $clinica->name }}</p>
    </div>

    <form method="POST" action="{{ route('painel.clinicas.update', $clinica) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Dados</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nome</label>
                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $clinica->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="legal_name" class="form-label">Razão social</label>
                        <input id="legal_name" type="text" class="form-control" name="legal_name" value="{{ old('legal_name', $clinica->legal_name) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="document" class="form-label">CNPJ</label>
                        <input id="document" type="text" class="form-control" name="document" value="{{ old('document', $clinica->document) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">E-mail de contato</label>
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $clinica->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label">Telefone</label>
                        <input id="phone" type="text" class="form-control" name="phone" value="{{ old('phone', $clinica->phone) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="whatsapp" class="form-label">WhatsApp</label>
                        <input id="whatsapp" type="text" class="form-control" name="whatsapp" value="{{ old('whatsapp', $clinica->whatsapp) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="evolution_instance" class="form-label">Instância da Evolution</label>
                        <input
                            id="evolution_instance"
                            type="text"
                            class="form-control @error('evolution_instance') is-invalid @enderror"
                            name="evolution_instance"
                            value="{{ old('evolution_instance', $clinica->evolution_instance) }}"
                            placeholder="clinica-malheiro"
                            pattern="[a-z0-9][a-z0-9._\-]*"
                        >
                        @error('evolution_instance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="evolution_token" class="form-label">Token da instância</label>
                        <input
                            id="evolution_token"
                            type="password"
                            autocomplete="off"
                            class="form-control @error('evolution_token') is-invalid @enderror"
                            name="evolution_token"
                            value="{{ old('evolution_token') }}"
                            placeholder="{{ \App\Support\EvolutionToken::mask($clinica->evolution_token) ?? 'não cadastrado' }}"
                        >
                        @error('evolution_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">
                            @if ($clinica->evolution_token)
                                Guardado como {{ \App\Support\EvolutionToken::mask($clinica->evolution_token) }}.
                                Deixe vazio para manter.
                            @else
                                Nenhum token cadastrado — a Evolution recusa o envio com 401.
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="timezone" class="form-label">Fuso horário</label>
                        <input id="timezone" type="text" class="form-control @error('timezone') is-invalid @enderror" name="timezone" value="{{ old('timezone', $clinica->timezone) }}" required>
                        @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-text mt-2">
                    A instância só aceita minúsculas, números, ponto, hífen ou underscore. É o nome
                    com que o webhook descobre a dona da mensagem: divergência aqui é mensagem
                    descartada sem erro.
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Acesso</h2>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="vertical" class="form-label">Vertical principal</label>
                        <select id="vertical" class="form-select @error('vertical') is-invalid @enderror" name="vertical">
                            @foreach (\App\Services\Tenant\VerticalManager::VERTICAIS as $opcao)
                                <option value="{{ $opcao }}" @selected(old('vertical', $clinica->vertical ?? 'odontologia') === $opcao)>
                                    {{ \App\Services\Tenant\VerticalManager::rotulo($opcao) }}
                                </option>
                            @endforeach
                        </select>
                        @error('vertical')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">
                            Define quais módulos a clínica enxerga. Trocar a vertical não apaga
                            nada do banco da clínica.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Estado</label>
                        <select id="status" class="form-select" name="status">
                            @foreach ($estados as $chave => $rotulo)
                                <option value="{{ $chave }}" @selected(old('status', $clinica->status) === $chave)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $clinica->is_active))>
                            <label class="form-check-label" for="is_active">Clínica habilitada</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="suspension_reason" class="form-label">Motivo (suspensão)</label>
                        <input id="suspension_reason" type="text" class="form-control" name="suspension_reason" value="{{ old('suspension_reason', $clinica->suspension_reason) }}">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12">
                        <label class="form-label d-block">Verticais habilitadas</label>
                        <input type="hidden" name="verticals_present" value="1">
                        <div class="d-flex flex-wrap gap-3">
                            @foreach (\App\Services\Tenant\VerticalManager::VERTICAIS as $opcao)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="verticals[]"
                                           id="vertical_habilitada_{{ $opcao }}" value="{{ $opcao }}"
                                           @checked(in_array($opcao, old('verticals', $clinica->verticals ?? [$clinica->vertical ?? 'odontologia']), true))>
                                    <label class="form-check-label" for="vertical_habilitada_{{ $opcao }}">
                                        {{ \App\Services\Tenant\VerticalManager::rotulo($opcao) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('verticals')<div class="text-danger small">{{ $message }}</div>@enderror
                        @error('verticals.*')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="form-text">
                            Uma clínica pode ter mais de uma vertical (ex.: fisioterapia, Pilates e
                            depilação a laser). A vertical principal entra sempre na lista.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
            <a href="{{ route('painel.clinicas.show', $clinica) }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
