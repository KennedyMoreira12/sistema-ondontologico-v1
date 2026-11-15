@extends('layouts.app')

@section('title', 'Assinaturas — ' . $paciente->nome)

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.prontuario', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Histórico de assinaturas</h1>
                <p class="text-muted small mb-0">{{ $paciente->nome }}</p>
            </div>
        </div>
        @can('prontuario.view')
            <a href="{{ route('pacientes.ficha.pdf', $paciente) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf me-1"></i>Ficha em PDF
            </a>
        @endcan
    </div>

    <div class="alert alert-warning small">
        <i class="bi bi-shield-check me-1"></i>
        Estas são assinaturas manuscritas eletrônicas, com hash do desenho e do conteúdo assinado.
        <strong>Não são assinaturas digitais qualificadas ICP-Brasil.</strong>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Documento</th>
                        <th>Assinado em</th>
                        <th>Responsável</th>
                        <th>Versão</th>
                        <th>Integridade</th>
                        <th>Situação</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assinaturas as $assinatura)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $assinatura->document_title }}</span>
                                <span class="d-block text-muted small">
                                    @if ($assinatura->document_type === \App\Models\DocumentSignature::TIPO_PRESENCA)
                                        Confirmação de presença
                                    @else
                                        Termo de responsabilidade
                                    @endif
                                </span>
                            </td>
                            <td>{{ $assinatura->signed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $assinatura->user?->name ?? '—' }}</td>
                            <td>v{{ $assinatura->term_version }}</td>
                            <td>
                                @if ($assinatura->integra())
                                    <span class="badge text-bg-success"><i class="bi bi-check2-circle me-1"></i>Íntegra</span>
                                @else
                                    <span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Divergente</span>
                                @endif
                                <span class="d-block text-muted small" title="{{ $assinatura->content_hash }}">
                                    {{ substr($assinatura->content_hash, 0, 12) }}…
                                </span>
                            </td>
                            <td>
                                @if ($assinatura->estaAtiva())
                                    <span class="badge text-bg-success">Ativa</span>
                                @else
                                    <span class="badge text-bg-secondary">Substituída</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('pacientes.assinaturas.imagem', [$paciente, $assinatura]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" title="Ver assinatura">
                                    <i class="bi bi-pen"></i>
                                </a>
                                @if ($assinatura->signable instanceof \App\Models\Contract)
                                    <a href="{{ route('contratos.pdf', $assinatura->signable) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" title="Ver documento">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                @else
                                    <a href="{{ route('pacientes.ficha.pdf', $paciente) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" title="Ver ficha">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Nenhuma assinatura registrada para este paciente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $assinaturas->links() }}
        </div>
    </div>
@endsection
