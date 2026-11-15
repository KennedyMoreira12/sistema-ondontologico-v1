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
                @if (vertical_permite('odontograma'))
                    <a href="{{ route('pacientes.odontograma.index', $paciente) }}" class="btn btn-outline-primary">
                        <i class="bi bi-grid-3x3-gap me-1"></i>Odontograma
                    </a>
                @endif
            @endcan
            @if (vertical_permite('avaliacao'))
                @can('avaliacao.view')
                    <a href="{{ route('pacientes.avaliacoes.index', $paciente) }}" class="btn btn-outline-primary">
                        <i class="bi bi-clipboard2-pulse me-1"></i>Avaliações
                    </a>
                @endcan
            @endif
            @if (vertical_permite('evolucao'))
                @can('evolucao.view')
                    <a href="{{ route('pacientes.evolucoes-sessao.index', $paciente) }}" class="btn btn-outline-primary">
                        <i class="bi bi-activity me-1"></i>Evoluções
                    </a>
                @endcan
            @endif
            @if (vertical_permite('pacotes'))
                @can('pacotes.view')
                    <a href="{{ route('pacientes.pacotes.index', $paciente) }}" class="btn btn-outline-primary">
                        <i class="bi bi-box-seam me-1"></i>Pacotes
                    </a>
                @endcan
            @endif
            @can('prontuario.view')
                <a href="{{ route('pacientes.prescricoes.index', $paciente) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-capsule me-1"></i>Prescrições
                </a>
                <a href="{{ route('pacientes.ficha.pdf', $paciente) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Ficha em PDF
                </a>
                <a href="{{ route('pacientes.assinaturas.index', $paciente) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-pen me-1"></i>Assinaturas
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

            @php
                $assinaturaTermo = $termo?->assinaturaAtiva;
                $textoTermo = $termo?->conteudo ?: \App\Models\Contract::textoTermoPadrao($paciente->nome, tenant()?->name);
                $podeAssinarTermo = auth()->user()?->hasPermission('contratos.create')
                    || auth()->user()?->hasPermission('contratos.update')
                    || auth()->user()?->hasPermission('prontuario.create');
            @endphp

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-text me-2"></i>Termo de responsabilidade</span>
                    @if ($assinaturaTermo)
                        <span class="badge text-bg-success">Assinado</span>
                    @else
                        <span class="badge text-bg-warning">Pendente</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="border rounded p-2 mb-3 small bg-light"
                         style="max-height: 220px; overflow-y: auto; white-space: pre-line;">{{ $textoTermo }}</div>

                    @if ($assinaturaTermo)
                        <p class="small text-muted mb-2">
                            <i class="bi bi-check2-circle text-success me-1"></i>
                            Assinado em {{ $assinaturaTermo->signed_at?->format('d/m/Y H:i') }}
                            @if ($assinaturaTermo->user)
                                por {{ $assinaturaTermo->user->name }}
                            @endif
                            — versão {{ $assinaturaTermo->term_version }}.
                        </p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('contratos.pdf', $termo) }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Ver termo assinado
                            </a>
                            <a href="{{ route('pacientes.assinaturas.imagem', [$paciente, $assinaturaTermo]) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                                <i class="bi bi-pen me-1"></i>Ver assinatura
                            </a>
                        </div>
                    @elseif ($podeAssinarTermo)
                        <p class="small text-muted">
                            O paciente leu o termo acima. Confirme a assinatura manuscrita para registrar o aceite.
                        </p>
                        <x-assinatura-canvas
                            :action="route('pacientes.assinaturas.termo', $paciente)"
                            :modal-id="'assinatura-termo-'.$paciente->id"
                            title="Assinatura do termo de responsabilidade"
                            :document-title="$termo ? 'Termo v'.$termo->versao : 'Termo padrão da clínica'"
                            :patient-name="$paciente->nome"
                            button-label="Assinar documento"
                            button-class="btn btn-primary w-100"
                            icon="bi-pen"
                            confirm-label="Confirmar assinatura"
                        />
                    @else
                        <p class="small text-muted mb-0">Você não tem permissão para registrar a assinatura do termo.</p>
                    @endif

                    <a href="{{ route('pacientes.assinaturas.index', $paciente) }}" class="btn btn-link btn-sm px-0 mt-2">
                        Histórico de assinaturas
                    </a>
                </div>
            </div>
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