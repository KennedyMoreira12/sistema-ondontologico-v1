@extends('layouts.app')

@section('title', 'Emitir Atestado Odontológico')
@section('page-title', 'Emitir Atestado Odontológico')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-medical me-2"></i>Novo Atestado Odontológico</h5>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('documentos.atestado.pdf') }}" target="_blank" id="form-atestado">
                    @csrf

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="paciente_id" class="form-label fw-semibold">Paciente <span class="text-danger">*</span></label>
                            <select name="paciente_id" id="paciente_id" class="form-select" required>
                                <option value="">Selecione o paciente...</option>
                                @foreach ($pacientes as $paciente)
                                    <option value="{{ $paciente->id }}" data-cpf="{{ $paciente->cpf }}" data-rg="{{ $paciente->rg }}">
                                        {{ $paciente->nome }} {{ $paciente->cpf ? '(' . $paciente->cpf . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="profissional_id" class="form-label fw-semibold">Profissional responsável <span class="text-danger">*</span></label>
                            <select name="profissional_id" id="profissional_id" class="form-select" required>
                                <option value="">Selecione o profissional...</option>
                                @foreach ($profissionais as $prof)
                                    <option value="{{ $prof->id }}"
                                        data-cro="{{ $prof->registroConselho() }}"
                                        data-especialidades="{{ $prof->specialties->pluck('name')->implode(', ') }}">
                                        {{ $prof->nome }} ({{ $prof->registroConselho() }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="atendimento_data" class="form-label fw-semibold">Data do atendimento <span class="text-danger">*</span></label>
                            <input type="date" name="atendimento_data" id="atendimento_data" class="form-control" required value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
                        </div>

                        <div class="col-md-4">
                            <label for="atendimento_hora" class="form-label">Horário do atendimento</label>
                            <input type="time" name="atendimento_hora" id="atendimento_hora" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label for="dias_repouso" class="form-label fw-semibold">Dias de repouso <span class="text-danger">*</span></label>
                            <input type="number" name="dias_repouso" id="dias_repouso" class="form-control" required min="1" max="90" value="1">
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label for="local" class="form-label">Local de emissão</label>
                            <input type="text" name="local" id="local" class="form-control" placeholder="Cidade/Estado (preenchido automaticamente se vazio)">
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Gerar PDF do Atestado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const pacienteSelect = document.getElementById('paciente_id');
    const profissionalSelect = document.getElementById('profissional_id');
    
    // Preenchimento automático via AJAX (opcional)
    pacienteSelect.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if (option.dataset.cpf) {
            console.log('Paciente selecionado:', option.textContent);
        }
    });
    
    profissionalSelect.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if (option.dataset.cro) {
            console.log('Profissional:', option.textContent, 'CRO:', option.dataset.cro);
        }
    });
    
    // Validação antes de enviar
    document.getElementById('form-atestado').addEventListener('submit', function(e) {
        const dias = parseInt(document.getElementById('dias_repouso').value);
        if (dias < 1 || dias > 90) {
            e.preventDefault();
            alert('Dias de repouso deve ser entre 1 e 90.');
            return false;
        }
    });
});
</script>
@endpush