@extends('layouts.app')

@section('title', 'Editar profissional')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Editar profissional</h1>
        <a href="{{ route('profissionais.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('profissionais.update', $profissional) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" id="nome" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $profissional->nome) }}" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" id="cpf" name="cpf" class="form-control cpf-mask @error('cpf') is-invalid @enderror" value="{{ old('cpf', $profissional->cpf) }}" maxlength="14">
                        @error('cpf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if (vertical_permite('odontograma') || $profissional->tipo === 'dentista')
                        <div class="col-md-3">
                            <label for="cro" class="form-label">CRO</label>
                            <input type="text" id="cro" name="cro" class="form-control @error('cro') is-invalid @enderror" value="{{ old('cro', $profissional->cro) }}" maxlength="30">
                            @error('cro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    <div class="col-md-3">
                        <label for="registro_profissional" class="form-label">Registro profissional</label>
                        <input type="text" id="registro_profissional" name="registro_profissional" class="form-control @error('registro_profissional') is-invalid @enderror" value="{{ old('registro_profissional', $profissional->registro_profissional) }}" maxlength="50">
                        <div class="form-text">CREFITO na fisioterapia.</div>
                        @error('registro_profissional')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="conselho_regional" class="form-label">Conselho regional</label>
                        <input type="text" id="conselho_regional" name="conselho_regional" class="form-control @error('conselho_regional') is-invalid @enderror" value="{{ old('conselho_regional', $profissional->conselho_regional) }}" maxlength="20" placeholder="Ex.: CREFITO-1">
                        @error('conselho_regional')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="tipo" class="form-label">Tipo *</label>
                        <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                            <option value="dentista" @selected(old('tipo', $profissional->tipo) === 'dentista')>Dentista</option>
                            <option value="fisioterapeuta" @selected(old('tipo', $profissional->tipo) === 'fisioterapeuta')>Fisioterapeuta</option>
                            <option value="auxiliar" @selected(old('tipo', $profissional->tipo) === 'auxiliar')>Auxiliar</option>
                            <option value="recepcionista" @selected(old('tipo', $profissional->tipo) === 'recepcionista')>Recepcionista</option>
                            <option value="outro" @selected(old('tipo', $profissional->tipo) === 'outro')>Outro</option>
                        </select>
                        @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="comissao" class="form-label">Comissão (%)</label>
                        <input type="number" step="0.01" min="0" max="100" id="comissao" name="comissao" class="form-control @error('comissao') is-invalid @enderror" value="{{ old('comissao', $profissional->comissao) }}">
                        @error('comissao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="is_active" class="form-label">Status</label>
                        <select id="is_active" name="is_active" class="form-select">
                            <option value="1" @selected(old('is_active', $profissional->is_active ? '1' : '0') === '1')>Ativo</option>
                            <option value="0" @selected(old('is_active', $profissional->is_active ? '1' : '0') === '0')>Inativo</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="specialties" class="form-label">Especialidades</label>
                        <select id="specialties" name="specialties[]" class="form-select" multiple>
                            @foreach ($especialidades as $especialidade)
                                <option value="{{ $especialidade->id }}" @selected(in_array($especialidade->id, collect($profissional->specialties)->pluck('id')->all(), true))>{{ $especialidade->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Segure Ctrl para selecionar mais de uma.</div>
                        @error('specialties')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                    <a href="{{ route('profissionais.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection