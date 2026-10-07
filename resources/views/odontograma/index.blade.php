@extends('layouts.app')

@section('title', 'Odontograma — ' . $paciente->nome)

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Odontograma de {{ $paciente->nome }}</h1>
                <p class="text-muted small mb-0">
                    {{ $payload['odontogram']['titulo'] }} • atualizado em
                    <span id="odontograma-atualizado">{{ $payload['odontogram']['atualizado_em'] ?? '—' }}</span>
                </p>
            </div>
        </div>
        <a href="{{ route('pacientes.prontuario', $paciente) }}" class="btn btn-outline-primary">
            <i class="bi bi-journal-medical me-1"></i>Prontuário
        </a>
    </div>

    <div id="odontograma-alert" class="alert d-none" role="alert"></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-semibold me-auto"><i class="bi bi-grid-3x3-gap me-1"></i>Odontograma</span>

                    <label class="small text-muted mb-0 me-1" for="odontograma-denticao">Dentição</label>
                    <select id="odontograma-denticao" class="form-select form-select-sm d-inline-block w-auto">
                        @foreach ($payload['denticoes'] as $chave => $label)
                            <option value="{{ $chave }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <label class="small text-muted mb-0 me-1" for="odontograma-notacao">Notação</label>
                    <select id="odontograma-notacao" class="form-select form-select-sm d-inline-block w-auto">
                        @foreach ($payload['notacoes'] as $chave => $label)
                            <option value="{{ $chave }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        @foreach ($payload['camadas'] as $chave => $camada)
                            <span class="d-inline-flex align-items-center gap-1 small">
                                <span class="odontograma-dot" style="background: {{ $camada['cor'] }}"></span>
                                {{ $camada['label'] }}
                            </span>
                        @endforeach
                        <span class="text-muted small ms-auto"><i class="bi bi-info-circle me-1"></i>Clique numa face do dente para marcar.</span>
                    </div>

                    <div id="odontograma-arcadas" aria-live="polite"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-journal-text me-1"></i>Notas do odontograma</div>
                <div class="card-body">
                    <textarea id="odontograma-notas" rows="4" class="form-control"
                        placeholder="Evolução do tratamento, plano, oclusão...">{{ $payload['odontogram']['notas'] }}</textarea>
                    <button id="odontograma-notas-salvar" class="btn btn-sm btn-primary mt-2">
                        <i class="bi bi-check-lg me-1"></i>Salvar notas
                    </button>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-list-check me-1"></i>Catálogo de condições</span>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($payload['catalogo'] as $condicao)
                        <li class="list-group-item d-flex align-items-center gap-2 py-2 small">
                            <span class="odontograma-dot" style="background: {{ $condicao['cor'] ?? '#6c757d' }}"></span>
                            {{ $condicao['name'] }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i>Histórico de alterações</div>
                <div class="card-body">
                    <button type="button" id="odontograma-historico-btn" class="btn btn-outline-secondary btn-sm w-100"
                        data-bs-toggle="modal" data-bs-target="#odontograma-historico-modal">
                        <i class="bi bi-clock-history me-1"></i>Ver histórico
                    </button>
                </div>
            </div>
        </div>
    </div>

    @can('odontograma.update')
        <div class="modal fade" id="toothModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Dente <span id="toothNumero" class="text-primary"></span>
                            <span id="toothNome" class="text-muted fw-normal fs-6"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="toothNumeroInput">

                        <div id="toothNotacoes" class="mb-3"></div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label for="toothStatus" class="form-label small">Situação clínica</label>
                                <select id="toothStatus" class="form-select form-select-sm">
                                    @foreach ($payload['status_dente'] as $chave => $label)
                                        <option value="{{ $chave }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label for="toothSuperficie" class="form-label small">Região/superfície</label>
                                <select id="toothSuperficie" class="form-select form-select-sm"></select>
                                <div id="toothSuperficieDica" class="form-text small"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Camada</label>
                            <div class="btn-group w-100" role="group" aria-label="Camada da condição">
                                @foreach ($payload['camadas'] as $chave => $camada)
                                    <input type="radio" class="btn-check" name="toothCamada" id="toothCamada{{ ucfirst($chave) }}"
                                        value="{{ $chave }}" @checked($chave === 'existente')>
                                    <label class="btn btn-outline-secondary btn-sm"
                                        for="toothCamada{{ ucfirst($chave) }}">{{ $camada['label'] }}</label>
                                @endforeach
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-7">
                                <label for="toothCondicao" class="form-label small">Condição</label>
                                <select id="toothCondicao" class="form-select form-select-sm">
                                    <option value="" selected>Escolha a condição...</option>
                                    @foreach ($payload['catalogo'] as $condicao)
                                        <option value="{{ $condicao['id'] }}" data-cor="{{ $condicao['cor'] ?? '' }}">
                                            {{ $condicao['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <label for="toothDescricao" class="form-label small">Detalhe</label>
                                <input type="text" id="toothDescricao" class="form-control form-control-sm"
                                    placeholder="Ex.: cárie na fissura">
                            </div>
                        </div>

                        <button type="button" id="toothCondicaoAdd" class="btn btn-primary btn-sm w-100 mb-3">
                            <i class="bi bi-plus-lg me-1"></i>Adicionar condição
                        </button>

                        <label class="form-label small">Condições registradas</label>
                        <ul id="toothCondicoesLista" class="list-group list-group-flush border rounded mb-3"></ul>

                        <label for="toothObservacao" class="form-label small">Observação geral</label>
                        <textarea id="toothObservacao" rows="2" class="form-control form-control-sm"
                            placeholder="Observações deste dente..."></textarea>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" id="toothLimpar" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-eraser me-1"></i>Limpar (hígido)
                        </button>
                        <button type="button" id="toothSalvar" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Salvar dente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endcan

    <div class="modal fade" id="odontograma-historico-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clock-history me-1"></i>Histórico do odontograma</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="odontograma-historico-spinner" class="d-flex justify-content-center py-4">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div>
                    </div>
                    <div id="odontograma-historico" class="list-group list-group-flush"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-title', 'Odontograma')

@push('scripts')
    @php
        $odontogramaConfig = [
            'canEdit' => auth()->user()->can('odontograma.update'),
            'modalId' => 'toothModal',
            'urls' => [
                'settings' => route('pacientes.odontograma.settings', $paciente),
                'toothUpdate' => route('pacientes.odontograma.tooth.update', [$paciente, 'DENTE_ID']),
                'toothDestroy' => route('pacientes.odontograma.tooth.destroy', [$paciente, 'DENTE_ID']),
                'conditionStore' => route('pacientes.odontograma.condition.store', [$paciente, 'DENTE_ID']),
                'conditionDestroy' => route('pacientes.odontograma.condition.destroy', [$paciente, 'DENTE_ID', 'CONDICAO_ID']),
                'historico' => route('pacientes.odontograma.historico', $paciente),
            ],
        ];
    @endphp
    <script>
        window.odontogramaConfig = @json($odontogramaConfig);
        window.odontograma = @json($payload);
    </script>
    @vite(['resources/js/odontograma.js'])
@endpush