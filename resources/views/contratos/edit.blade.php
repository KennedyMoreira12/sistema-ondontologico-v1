@extends('layouts.app')

@section('title', 'Editar contrato')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Editar contrato</h1>
        <a href="{{ route('contratos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('contratos.update', $contrato) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="paciente_id" class="form-label">Paciente *</label>
                        <select id="paciente_id" name="paciente_id" class="form-select @error('paciente_id') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach ($pacientes as $paciente)
                                <option value="{{ $paciente->id }}" @selected(old('paciente_id', $contrato->paciente_id) == $paciente->id)>{{ $paciente->nome }}</option>
                            @endforeach
                        </select>
                        @error('paciente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="tipo" class="form-label">Tipo *</label>
                        <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach (['consentimento', 'tratamento', 'responsabilidade', 'procuração', 'exames', 'outros'] as $tipo)
                                <option value="{{ $tipo }}" @selected(old('tipo', $contrato->tipo) === $tipo)>{{ ucfirst($tipo) }}</option>
                            @endforeach
                        </select>
                        @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status *</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach (['pendente', 'assinado', 'cancelado'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $contrato->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="assinado_em" class="form-label">Assinado em</label>
                        <input type="datetime-local" id="assinado_em" name="assinado_em" class="form-control @error('assinado_em') is-invalid @enderror" value="{{ old('assinado_em', $contrato->assinado_em?->format('Y-m-d\TH:i')) }}">
                        @error('assinado_em')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="conteudo" class="form-label">Conteúdo</label>
                        <textarea id="conteudo" name="conteudo" class="form-control @error('conteudo') is-invalid @enderror" rows="6" placeholder="Texto do contrato, termos ou condições...">{{ old('conteudo', $contrato->conteudo) }}</textarea>
                        @error('conteudo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                    <a href="{{ route('contratos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection