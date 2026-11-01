@extends('layouts.app')

@section('title', 'Nota fiscal #'.$notaFiscal->id)

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">
                Nota fiscal #{{ $notaFiscal->id }}
                <span class="badge text-bg-{{ $notaFiscal->statusClass() }} align-middle">{{ $notaFiscal->statusLabel() }}</span>
            </h1>
            <p class="text-muted small mb-0">
                {{ strtoupper($notaFiscal->tipo) }} · {{ $notaFiscal->tomador_nome ?: ($notaFiscal->paciente?->nome ?? 'Sem tomador') }}
            </p>
        </div>
        <a href="{{ route('fiscal.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>

    @if ($notaFiscal->status === \App\Models\NotaFiscal::STATUS_REJEITADA && $notaFiscal->motivo_rejeicao)
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon me-1"></i>Nota rejeitada</div>
            {{ $notaFiscal->motivo_rejeicao }}
        </div>
    @endif

    @if ($notaFiscal->status === \App\Models\NotaFiscal::STATUS_CANCELADA)
        <div class="alert alert-dark">
            <div class="fw-semibold mb-1">Nota cancelada em {{ $notaFiscal->cancelada_em?->format('d/m/Y H:i') }}</div>
            {{ $notaFiscal->motivo_cancelamento }}
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-2"></i>Documento</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Número / série</div>
                            <div class="fw-semibold">{{ $notaFiscal->numero ?? '—' }} / {{ $notaFiscal->serie ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Emissão</div>
                            <div class="fw-semibold">{{ $notaFiscal->emitida_em?->format('d/m/Y H:i') ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Provedor / ambiente</div>
                            <div class="fw-semibold">{{ $notaFiscal->provider ?: $provider }} · {{ $notaFiscal->ambiente ?? config('fiscal.ambiente') }}</div>
                        </div>

                        @if ($notaFiscal->chave)
                            <div class="col-md-8">
                                <div class="text-muted small">Chave de acesso</div>
                                <div class="fw-semibold font-monospace text-break">{{ $notaFiscal->chave }}</div>
                            </div>
                        @endif

                        @if ($notaFiscal->protocolo)
                            <div class="col-md-4">
                                <div class="text-muted small">Protocolo</div>
                                <div class="fw-semibold font-monospace">{{ $notaFiscal->protocolo }}</div>
                            </div>
                        @endif
                    </div>

                    <hr>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Valor do serviço</div>
                            <div class="fw-semibold">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Desconto</div>
                            <div class="fw-semibold">R$ {{ number_format($notaFiscal->valor_desconto ?? 0, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Líquido</div>
                            <div class="fw-semibold">R$ {{ number_format($notaFiscal->valorLiquido(), 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">ISS ({{ number_format($notaFiscal->aliquota_iss ?? 0, 2, ',', '.') }}%)</div>
                            <div class="fw-semibold">R$ {{ number_format($notaFiscal->valor_iss ?? 0, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-md-8">
                            <div class="text-muted small">Serviço</div>
                            <div class="fw-semibold">
                                {{ $notaFiscal->codigo_servico ?? config('fiscal.codigo_servico') }} —
                                {{ $notaFiscal->descricao_servico ?? config('fiscal.descricao_servico') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($notaFiscal->status === \App\Models\NotaFiscal::STATUS_RASCUNHO)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square me-2"></i>Editar rascunho</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('fiscal.update', $notaFiscal) }}">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="valor_total" class="form-label">Valor do serviço</label>
                                    <input type="number" step="0.01" min="0.01" id="valor_total" name="valor_total"
                                        class="form-control" value="{{ old('valor_total', $notaFiscal->valor_total) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="valor_desconto" class="form-label">Desconto</label>
                                    <input type="number" step="0.01" min="0" id="valor_desconto" name="valor_desconto"
                                        class="form-control" value="{{ old('valor_desconto', $notaFiscal->valor_desconto ?? '0.00') }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="aliquota_iss" class="form-label">Alíquota ISS (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" id="aliquota_iss" name="aliquota_iss"
                                        class="form-control" value="{{ old('aliquota_iss', $notaFiscal->aliquota_iss ?? config('fiscal.aliquota_iss')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="tipo" class="form-label">Tipo</label>
                                    <select id="tipo" name="tipo" class="form-select" required>
                                        @foreach (['nfse' => 'NFS-e', 'nfe' => 'NF-e', 'nfce' => 'NFC-e'] as $valor => $label)
                                            <option value="{{ $valor }}" @selected(old('tipo', $notaFiscal->tipo) === $valor)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="serie" class="form-label">Série</label>
                                    <input type="text" id="serie" name="serie" class="form-control"
                                        value="{{ old('serie', $notaFiscal->serie ?? config('fiscal.serie')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="codigo_servico" class="form-label">Código do serviço</label>
                                    <input type="text" id="codigo_servico" name="codigo_servico" class="form-control"
                                        value="{{ old('codigo_servico', $notaFiscal->codigo_servico ?? config('fiscal.codigo_servico')) }}">
                                </div>
                                <div class="col-12">
                                    <label for="descricao_servico" class="form-label">Descrição do serviço</label>
                                    <input type="text" id="descricao_servico" name="descricao_servico" class="form-control"
                                        value="{{ old('descricao_servico', $notaFiscal->descricao_servico ?? config('fiscal.descricao_servico')) }}">
                                </div>

                                <div class="col-md-6">
                                    <label for="tomador_nome" class="form-label">Tomador</label>
                                    <input type="text" id="tomador_nome" name="tomador_nome" class="form-control"
                                        value="{{ old('tomador_nome', $notaFiscal->tomador_nome ?? $notaFiscal->paciente?->nome) }}">
                                </div>
                                <div class="col-md-3">
                                    <label for="tomador_documento" class="form-label">CPF / CNPJ</label>
                                    <input type="text" id="tomador_documento" name="tomador_documento" class="form-control"
                                        value="{{ old('tomador_documento', $notaFiscal->tomador_documento ?? $notaFiscal->paciente?->cpf) }}">
                                </div>
                                <div class="col-md-3">
                                    <label for="tomador_email" class="form-label">E-mail</label>
                                    <input type="email" id="tomador_email" name="tomador_email" class="form-control"
                                        value="{{ old('tomador_email', $notaFiscal->tomador_email ?? $notaFiscal->paciente?->email) }}">
                                </div>
                                <div class="col-md-7">
                                    <label for="tomador_endereco" class="form-label">Endereço</label>
                                    <input type="text" id="tomador_endereco" name="tomador_endereco" class="form-control"
                                        value="{{ old('tomador_endereco', $notaFiscal->tomador_endereco ?? $notaFiscal->paciente?->endereco) }}">
                                </div>
                                <div class="col-md-3">
                                    <label for="tomador_cidade" class="form-label">Cidade</label>
                                    <input type="text" id="tomador_cidade" name="tomador_cidade" class="form-control"
                                        value="{{ old('tomador_cidade', $notaFiscal->tomador_cidade ?? $notaFiscal->paciente?->cidade) }}">
                                </div>
                                <div class="col-md-1">
                                    <label for="tomador_uf" class="form-label">UF</label>
                                    <input type="text" id="tomador_uf" name="tomador_uf" maxlength="2" class="form-control text-uppercase"
                                        value="{{ old('tomador_uf', $notaFiscal->tomador_uf ?? $notaFiscal->paciente?->estado) }}">
                                </div>
                                <div class="col-md-1">
                                    <label for="tomador_cep" class="form-label">CEP</label>
                                    <input type="text" id="tomador_cep" name="tomador_cep" class="form-control"
                                        value="{{ old('tomador_cep', $notaFiscal->tomador_cep ?? $notaFiscal->paciente?->cep) }}">
                                </div>

                                @if ($notaFiscal->paciente_id)
                                    <input type="hidden" name="paciente_id" value="{{ $notaFiscal->paciente_id }}">
                                @endif
                                @if ($notaFiscal->consulta_id)
                                    <input type="hidden" name="consulta_id" value="{{ $notaFiscal->consulta_id }}">
                                @endif
                            </div>

                            <button type="submit" class="btn btn-outline-primary mt-3">
                                <i class="bi bi-save me-1"></i>Salvar alterações
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Histórico</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-3">Criada em</dt>
                        <dd class="col-sm-9">{{ $notaFiscal->created_at?->format('d/m/Y H:i') }}</dd>
                        <dt class="col-sm-3">Tentativas de emissão</dt>
                        <dd class="col-sm-9">{{ $notaFiscal->tentativas ?? 0 }}</dd>
                        <dt class="col-sm-3">Última tentativa</dt>
                        <dd class="col-sm-9">{{ $notaFiscal->ultima_tentativa_em?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </dl>

                    @if (! empty($notaFiscal->dados['resposta']))
                        <details class="mt-3">
                            <summary class="text-muted small">Resposta do provedor</summary>
                            <pre class="bg-light border rounded p-2 mt-2 small mb-0" style="max-height: 260px; overflow: auto;">{{ json_encode($notaFiscal->dados['resposta'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-lightning me-2"></i>Ações</div>
                <div class="card-body d-grid gap-2">
                    @if ($podeEmitir)
                        @can('fiscal.emitir')
                            <form method="POST" action="{{ route('fiscal.emitir', $notaFiscal) }}"
                                onsubmit="return confirm('Emitir a nota fiscal agora? Depois disso ela só pode ser cancelada.');">
                                @csrf
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-send me-1"></i>Emitir nota
                                </button>
                            </form>
                        @endcan
                    @endif

                    @if ($notaFiscal->status !== \App\Models\NotaFiscal::STATUS_RASCUNHO)
                        <form method="POST" action="{{ route('fiscal.consultar', $notaFiscal) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="bi bi-arrow-repeat me-1"></i>Consultar status
                            </button>
                        </form>
                    @endif

                    @if ($notaFiscal->status === \App\Models\NotaFiscal::STATUS_AUTORIZADA)
                        @can('fiscal.cancelar')
                            <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                                <i class="bi bi-x-octagon me-1"></i>Cancelar nota
                            </button>
                        @endcan
                    @endif

                    <a href="{{ route('fiscal.imprimir', $notaFiscal) }}" target="_blank" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-printer me-1"></i>Imprimir / PDF
                    </a>
                    <a href="{{ route('fiscal.xml', $notaFiscal) }}" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-filetype-xml me-1"></i>Baixar XML
                    </a>

                    @if ($notaFiscal->link_pdf)
                        <a href="{{ $notaFiscal->link_pdf }}" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-box-arrow-up-right me-1"></i>PDF do provedor
                        </a>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>Emitente</div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Paciente</span>
                        <span class="text-end">{{ $notaFiscal->paciente?->nome ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Agendamento</span>
                        <span class="text-end">{{ $notaFiscal->consulta_id ? '#'.$notaFiscal->consulta_id : '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Vínculo</span>
                        <span class="text-end">{{ $notaFiscal->consulta?->inicio?->format('d/m/Y H:i') ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($notaFiscal->status === \App\Models\NotaFiscal::STATUS_AUTORIZADA)
        @can('fiscal.cancelar')
            <div class="modal fade" id="modalCancelar" tabindex="-1" aria-labelledby="modalCancelarLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('fiscal.cancelar', $notaFiscal) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalCancelarLabel">Cancelar nota fiscal</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <label for="motivo" class="form-label">Motivo do cancelamento</label>
                                <textarea id="motivo" name="motivo" rows="3" minlength="15" maxlength="255" class="form-control" required></textarea>
                                <p class="text-muted small mb-0 mt-2">Mínimo de 15 caracteres, como exigem as prefeituras.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                                <button type="submit" class="btn btn-danger">Cancelar nota</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    @endif
@endsection
