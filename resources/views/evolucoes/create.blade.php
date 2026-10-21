@extends('layouts.app')

@section('title', 'Nova evolução de sessão')

@section('page-title', 'Nova evolução de sessão')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.evolucoes-sessao.index', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Nova evolução de sessão</h1>
                <p class="text-muted small mb-0">{{ $paciente->nome }}</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('pacientes.evolucoes-sessao.store', $paciente) }}">
        @csrf

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="data" class="form-label">Data da sessão *</label>
                        <input type="date" id="data" name="data" class="form-control @error('data') is-invalid @enderror" value="{{ old('data', now()->toDateString()) }}" required>
                        @error('data')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-8">
                        <label for="avaliacao_id" class="form-label">Ficha de avaliação de origem</label>
                        <select id="avaliacao_id" name="avaliacao_id" class="form-select @error('avaliacao_id') is-invalid @enderror">
                            <option value="">— Sem vínculo —</option>
                            @foreach ($avaliacoes as $avaliacao)
                                <option value="{{ $avaliacao->id }}" @selected((int) old('avaliacao_id') === $avaliacao->id)>
                                    {{ $avaliacao->data->format('d/m/Y') }} — {{ \Illuminate\Support\Str::limit($avaliacao->queixa_principal, 60) }}
                                </option>
                            @endforeach
                        </select>
                        @error('avaliacao_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="conteudo" class="form-label">Registro da sessão *</label>
                        <textarea id="conteudo" name="conteudo" rows="4" class="form-control @error('conteudo') is-invalid @enderror" required>{{ old('conteudo') }}</textarea>
                        @error('conteudo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="tecnicas" class="form-label">Condutas e técnicas</label>
                        <textarea id="tecnicas" name="tecnicas" rows="3" class="form-control @error('tecnicas') is-invalid @enderror">{{ old('tecnicas') }}</textarea>
                        @error('tecnicas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="resposta_paciente" class="form-label">Resposta do paciente</label>
                        <textarea id="resposta_paciente" name="resposta_paciente" rows="2" class="form-control @error('resposta_paciente') is-invalid @enderror">{{ old('resposta_paciente') }}</textarea>
                        @error('resposta_paciente')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label for="dor_antes" class="form-label">Dor antes (0–10)</label>
                        <input type="number" id="dor_antes" name="dor_antes" min="0" max="10" class="form-control @error('dor_antes') is-invalid @enderror" value="{{ old('dor_antes') }}">
                        @error('dor_antes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label for="dor_depois" class="form-label">Dor depois (0–10)</label>
                        <input type="number" id="dor_depois" name="dor_depois" min="0" max="10" class="form-control @error('dor_depois') is-invalid @enderror" value="{{ old('dor_depois') }}">
                        @error('dor_depois')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar rascunho</button>
                <a href="{{ route('pacientes.evolucoes-sessao.index', $paciente) }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </form>
@endsection
