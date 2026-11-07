@extends('layouts.app')

@section('title', 'Novo pacote de sessões')

@section('page-title', 'Novo pacote de sessões')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.pacotes.index', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Novo pacote de sessões</h1>
                <p class="text-muted small mb-0">{{ $paciente->nome }}</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('pacientes.pacotes.store', $paciente) }}">
        @csrf

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="nome" class="form-label">Nome do pacote *</label>
                        <input type="text" id="nome" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="total_sessoes" class="form-label">Total de sessões *</label>
                        <input type="number" id="total_sessoes" name="total_sessoes" min="1" max="1000" class="form-control @error('total_sessoes') is-invalid @enderror" value="{{ old('total_sessoes', 10) }}" required>
                        @error('total_sessoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="valor_sessao" class="form-label">Valor da sessão</label>
                        <input type="number" step="0.01" min="0" id="valor_sessao" name="valor_sessao" class="form-control @error('valor_sessao') is-invalid @enderror" value="{{ old('valor_sessao', 0) }}">
                        @error('valor_sessao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="valor_total" class="form-label">Valor total</label>
                        <input type="number" step="0.01" min="0" id="valor_total" name="valor_total" class="form-control @error('valor_total') is-invalid @enderror" value="{{ old('valor_total', 0) }}">
                        @error('valor_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="validade_em" class="form-label">Validade</label>
                        <input type="date" id="validade_em" name="validade_em" class="form-control @error('validade_em') is-invalid @enderror" value="{{ old('validade_em') }}">
                        @error('validade_em')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar pacote</button>
                <a href="{{ route('pacientes.pacotes.index', $paciente) }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </div>
    </form>
@endsection
