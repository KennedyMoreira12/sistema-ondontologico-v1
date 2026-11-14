@extends('layouts.app')

@section('title', 'Notas fiscais')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">Notas fiscais</h1>
            <p class="text-muted small mb-0">
                Emissão pelo provedor <span class="badge text-bg-light border">{{ $provider }}</span>
                · ambiente <span class="badge text-bg-light border">{{ config('fiscal.ambiente') }}</span>
            </p>
        </div>
        @can('fiscal.create')
            <a href="{{ route('fiscal.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nova nota
            </a>
        @endcan
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Autorizadas</div>
                    <div class="h4 mb-0">{{ (int) ($resumo['autorizada'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Faturamento autorizado</div>
                    <div class="h4 mb-0">R$ {{ number_format($totalAutorizado, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Rascunhos</div>
                    <div class="h4 mb-0">{{ (int) ($resumo['rascunho'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Rejeitadas</div>
                    <div class="h4 mb-0 text-danger">{{ (int) ($resumo['rejeitada'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('fiscal.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="busca" class="form-label">Buscar</label>
                    <input type="search" id="busca" name="busca" class="form-control" value="{{ request('busca') }}" placeholder="Paciente, número ou chave...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach (\App\Models\NotaFiscal::STATUS_LABELS as $valor => $label)
                            <option value="{{ $valor }}" @selected(request('status') === $valor)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select id="tipo" name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="nfse" @selected(request('tipo') === 'nfse')>NFS-e</option>
                        <option value="nfe" @selected(request('tipo') === 'nfe')>NF-e</option>
                        <option value="nfce" @selected(request('tipo') === 'nfce')>NFC-e</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Paciente / tomador</th>
                        <th>Tipo</th>
                        <th>Número</th>
                        <th class="text-end">Valor</th>
                        <th>Status</th>
                        <th>Emissão</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notas as $nota)
                        <tr>
                            <td class="text-muted">{{ $nota->id }}</td>
                            <td>
                                <a href="{{ route('fiscal.show', $nota) }}" class="text-decoration-none fw-semibold">
                                    {{ $nota->tomador_nome ?: ($nota->paciente?->nome ?? 'Sem tomador') }}
                                </a>
                                @if ($nota->tomador_documento)
                                    <div class="text-muted small">{{ $nota->tomador_documento }}</div>
                                @endif
                            </td>
                            <td><span class="badge text-bg-light border">{{ strtoupper($nota->tipo) }}</span></td>
                            <td>
                                {{ $nota->numero ?? '—' }}
                                @if ($nota->serie)
                                    <span class="text-muted small">/{{ $nota->serie }}</span>
                                @endif
                            </td>
                            <td class="text-end">R$ {{ number_format($nota->valor_total, 2, ',', '.') }}</td>
                            <td><span class="badge text-bg-{{ $nota->statusClass() }}">{{ $nota->statusLabel() }}</span></td>
                            <td>{{ $nota->emitida_em?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('fiscal.show', $nota) }}" class="btn btn-sm btn-outline-secondary" title="Detalhes">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if ($nota->podeEmitir())
                                    @can('fiscal.emitir')
                                        <form method="POST" action="{{ route('fiscal.emitir', $nota) }}" class="d-inline" onsubmit="return confirm('Emitir a nota fiscal agora?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Emitir">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                                @if ($nota->chave)
                                    <a href="{{ route('fiscal.xml', $nota) }}" class="btn btn-sm btn-outline-primary" title="XML">
                                        <i class="bi bi-filetype-xml"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Nenhuma nota fiscal encontrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($notas->hasPages())
            <div class="card-footer bg-white">
                {{ $notas->links() }}
            </div>
        @endif
    </div>

    @if ($configuracaoFiscal)
        <div class="mt-5 mb-3">
            <h2 class="h5 mb-0">Configuração fiscal</h2>
            <p class="text-muted small mb-0">Emissor SendNF e notas NFS-e desta clínica.</p>
        </div>

        @include('admin.fiscal.partials.configuracao', $configuracaoFiscal)
    @elseif ($motivoSemConfiguracao === 'schema')
        <div class="alert alert-warning mt-4" role="alert">
            <i class="bi bi-info-circle me-1"></i>
            A configuração fiscal ainda não está disponível nesta clínica. Fale com o suporte para ativar as tabelas fiscais.
        </div>
    @endif
@endsection
