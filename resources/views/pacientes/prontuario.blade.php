@extends('layouts.app')

@section('title', 'Prontuário — ' . $paciente->nome)

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Prontuário de {{ $paciente->nome }}</h1>
                <p class="text-muted small mb-0">Nascimento: {{ $paciente->nascimento?->translatedFormat('d/m/Y') ?? '—' }} • Idade: {{ $paciente->nascimento?->age ?? '—' }} anos</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('odontograma.view')
                <a href="{{ route('pacientes.odontograma.index', $paciente) }}" class="btn btn-outline-primary">
                    <i class="bi bi-grid-3x3-gap me-1"></i>Odontograma
                </a>
            @endcan
            @can('prontuario.view')
                <a href="{{ route('pacientes.prescricoes.index', $paciente) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-capsule me-1"></i>Prescrições
                </a>
            @endcan
        </div>
    </div>

    @if ($alertas->where('is_active', true)->isNotEmpty())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Alertas clínicos:</strong>
            @foreach ($alertas->where('is_active', true) as $alerta)
                <span class="badge bg-danger text-uppercase me-1">{{ $alerta->tipo }}</span>
            @endforeach
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            @can('prontuario.create')
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-2"></i>Registrar no prontuário</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pacientes.evolucoes.store', $paciente) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="tipo" class="form-label">Tipo de registro</label>
                                <select id="tipo" name="tipo" class="form-select">
                                    <option value="evolucao">Evolução</option>
                                    <option value="consulta">Anotação de consulta</option>
                                    <option value="procedimento">Procedimento</option>
                                    <option value="atestado">Atestado</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="conteudo" class="form-label">Conteúdo *</label>
                                <textarea id="conteudo" name="conteudo" rows="5" class="form-control" required placeholder="Descreva a evolução clínica..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Registrar</button>
                        </form>
                    </div>
                </div>
            @else
                <div class="card shadow-sm mb-3">
                    <div class="card-body text-center text-muted">
                        <i class="bi bi-lock fs-4"></i>
                        <p class="small mb-0 mt-1">Você tem permissão apenas para visualizar evoluções.</p>
                    </div>
                </div>
            @endcan
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Histórico clínico</div>
                <div class="card-body">
                    @forelse ($evolucoes as $evolucao)
                        <div class="d-flex gap-3 mb-4">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    <i class="bi bi-file-earmark-medical"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 border-bottom pb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold text-capitalize">{{ $evolucao->tipo }}</span>
                                    <span class="text-muted small">{{ $evolucao->created_at->translatedFormat('d/m/Y H:i') }}</span>
                                </div>
                                <div class="text-muted small mb-2">
                                    {{ $evolucao->profissional?->nome ?? 'Profissional não informado' }}
                                </div>
                                <div class="text-break">{{ $evolucao->conteudo }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center py-4 mb-0">Nenhuma evolução registrada ainda.</p>
                    @endforelse

                    <div class="d-flex justify-content-center">
                        {{ $evolucoes->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection