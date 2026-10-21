@extends('layouts.app')

@section('title', 'Editar ficha de avaliação')

@section('page-title', 'Editar ficha de avaliação')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.avaliacoes.index', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Editar ficha de avaliação</h1>
                <p class="text-muted small mb-0">{{ $paciente->nome }} — {{ $avaliacao->data->format('d/m/Y') }}</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('pacientes.avaliacoes.update', [$paciente, $avaliacao]) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="data" class="form-label">Data da avaliação *</label>
                        <input type="date" id="data" name="data" class="form-control @error('data') is-invalid @enderror" value="{{ old('data', $avaliacao->data->format('Y-m-d')) }}" required>
                        @error('data')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="queixa_principal" class="form-label">Queixa principal *</label>
                        <textarea id="queixa_principal" name="queixa_principal" rows="3" class="form-control @error('queixa_principal') is-invalid @enderror" required>{{ old('queixa_principal', $avaliacao->queixa_principal) }}</textarea>
                        @error('queixa_principal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="historia_clinica" class="form-label">História clínica</label>
                        <textarea id="historia_clinica" name="historia_clinica" rows="4" class="form-control @error('historia_clinica') is-invalid @enderror">{{ old('historia_clinica', $avaliacao->historia_clinica) }}</textarea>
                        @error('historia_clinica')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="exame_fisico" class="form-label">Exame físico</label>
                        <textarea id="exame_fisico" name="exame_fisico" rows="4" class="form-control @error('exame_fisico') is-invalid @enderror">{{ old('exame_fisico', $avaliacao->exame_fisico) }}</textarea>
                        @error('exame_fisico')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="hipotese_clinica" class="form-label">Hipótese clínica</label>
                        <textarea id="hipotese_clinica" name="hipotese_clinica" rows="2" class="form-control @error('hipotese_clinica') is-invalid @enderror">{{ old('hipotese_clinica', $avaliacao->hipotese_clinica) }}</textarea>
                        @error('hipotese_clinica')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="objetivos" class="form-label">Objetivos do tratamento</label>
                        <textarea id="objetivos" name="objetivos" rows="3" class="form-control @error('objetivos') is-invalid @enderror">{{ old('objetivos', $avaliacao->objetivos) }}</textarea>
                        @error('objetivos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="plano_terapeutico" class="form-label">Plano terapêutico</label>
                        <textarea id="plano_terapeutico" name="plano_terapeutico" rows="4" class="form-control @error('plano_terapeutico') is-invalid @enderror">{{ old('plano_terapeutico', $avaliacao->plano_terapeutico) }}</textarea>
                        @error('plano_terapeutico')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="2" class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes', $avaliacao->observacoes) }}</textarea>
                        @error('observacoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar alterações</button>
                <a href="{{ route('pacientes.avaliacoes.index', $paciente) }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </form>
@endsection
