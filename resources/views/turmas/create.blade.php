@extends('layouts.app')

@section('title', 'Nova turma')

@section('page-title', 'Nova turma')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('turmas.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Nova turma</h1>
            <p class="text-muted small mb-0">Dia, horário e profissional da turma.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('turmas.store') }}">
        @csrf

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="nome" class="form-label">Nome da turma *</label>
                        <input type="text" id="nome" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" placeholder="Ex.: Pilates Segunda 10h" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="servico" class="form-label">Serviço</label>
                        <input type="text" id="servico" name="servico" class="form-control @error('servico') is-invalid @enderror" value="{{ old('servico', 'PILATES') }}">
                        @error('servico')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="dia_semana" class="form-label">Dia da semana *</label>
                        <select id="dia_semana" name="dia_semana" class="form-select @error('dia_semana') is-invalid @enderror" required>
                            <option value="">Selecione</option>
                            @foreach (\App\Models\Turma::DIAS as $numero => $dia)
                                <option value="{{ $numero }}" @selected((int) old('dia_semana') === $numero)>{{ $dia }}</option>
                            @endforeach
                        </select>
                        @error('dia_semana')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label for="hora_inicio" class="form-label">Hora de início *</label>
                        <input type="time" id="hora_inicio" name="hora_inicio" class="form-control @error('hora_inicio') is-invalid @enderror" value="{{ old('hora_inicio') }}" required>
                        @error('hora_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label for="hora_fim" class="form-label">Hora de fim</label>
                        <input type="time" id="hora_fim" name="hora_fim" class="form-control @error('hora_fim') is-invalid @enderror" value="{{ old('hora_fim') }}">
                        @error('hora_fim')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="profissional_id" class="form-label">Profissional</label>
                        <select id="profissional_id" name="profissional_id" class="form-select @error('profissional_id') is-invalid @enderror">
                            <option value="">Sem profissional</option>
                            @foreach ($profissionais as $profissional)
                                <option value="{{ $profissional->id }}" @selected(old('profissional_id') == $profissional->id)>{{ $profissional->nome }}</option>
                            @endforeach
                        </select>
                        @error('profissional_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="capacidade" class="form-label">Capacidade</label>
                        <input type="number" id="capacidade" name="capacidade" min="1" max="500" class="form-control @error('capacidade') is-invalid @enderror" value="{{ old('capacidade') }}">
                        @error('capacidade')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-8">
                        <label for="sala" class="form-label">Sala</label>
                        <input type="text" id="sala" name="sala" class="form-control @error('sala') is-invalid @enderror" value="{{ old('sala') }}">
                        @error('sala')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="2" class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes') }}</textarea>
                        @error('observacoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar turma</button>
                <a href="{{ route('turmas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </form>
@endsection
