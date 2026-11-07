@extends('layouts.app')

@section('title', 'Nota fiscal #{{ $nfse->id }} - NFS-e')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Nota fiscal #{{ $nfse->id }}</h1>
            <p class="text-muted small mb-0">Detalhes, eventos e documentos da NFS-e da clínica.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.fiscal.nfse.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Houve um problema:</strong>
            <ul class="mb-0 mt-2 small">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-3" style="max-width: 760px;">
        <div class="card-header bg-white fw-semibold">Dados da nota</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <p class="form-control-plaintext"><span class="badge bg-{{ $nfse->status === 'authorized' ? 'success' : ($nfse->status === 'rejected' ? 'danger' : ($nfse->status === 'blocked' ? 'warning' : 'secondary')) }}">{{ ucfirst($nfse->status ?? 'draft') }}</span></p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nº SendNF</label>
                    <p class="form-control-plaintext">{{ $nfse->sendnf_id ?? 'Não transmitido' }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Protocolo</label>
                    <p class="form-control-plaintext">{{ $nfse->protocolo ?? '-' }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Chave de idempotência</label>
                    <p class="form-control-plaintext small">{{ $nfse->idempotency_key ?? '-' }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Chave de acesso</label>
                    <p class="form-control-plaintext small">{{ $nfse->chave_acesso ?? '-' }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tomador</label>
                    <p class="form-control-plaintext">{{ $nfse->tomador_nome ?? '-' }}<br><small class="text-muted">{{ $nfse->tomador_documento ?? '-' }}</small></p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Serviço</label>
                    <p class="form-control-plaintext">{{ $nfse->servico_descricao ?? '-' }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Valor do serviço</label>
                    <p class="form-control-plaintext">R$ {{ number_format($nfse->valor_servico ?? 0, 2, ',', '.') }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">ISS</label>
                    <p class="form-control-plaintext">R$ {{ number_format($nfse->valor_iss ?? 0, 2, ',', '.') }} ({{ number_format($nfse->aliquota ?? 0, 2, ',', '.') }}%)</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Competência</label>
                    <p class="form-control-plaintext">{{ $nfse->competencia?->format('d/m/Y') ?? '-' }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Emissão</label>
                    <p class="form-control-plaintext">{{ $nfse->data_emissao?->format('d/m/Y H:i:s') ?? '-' }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Autorizada em</label>
                    <p class="form-control-plaintext">{{ $nfse->authorized_at?->format('d/m/Y H:i:s') ?? '-' }}</p>
                </div>
                @if (filled($nfse->rejected_reason))
                    <div class="col-12">
                        <label class="form-label">Motivo da rejeição</label>
                        <p class="form-control-plaintext text-danger small">{{ $nfse->rejected_reason }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Documentos (PDF/XML) -->
    @if (filled($nfse->sendnf_id) && $nfse->status === 'authorized')
        <div class="card shadow-sm mb-3" style="max-width: 760px;">
            <div class="card-header bg-white fw-semibold">Documentos</div>
            <div class="card-body">
                <a href="{{ route('admin.fiscal.nfse.pdf', $nfse) }}" class="btn btn-outline-primary"><i class="bi bi-file-pdf me-1"></i>Baixar PDF (DANFSe)</a>
                <a href="{{ route('admin.fiscal.nfse.xml', $nfse) }}" class="btn btn-outline-secondary"><i class="bi bi-file-code me-1"></i>Baixar XML</a>
            </div>
        </div>
    @endif

    <!-- Pré-visualização (sem transmissão) -->
    <div class="card shadow-sm mb-3" style="max-width: 760px;">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-eye me-2"></i>Pré-visualizar na SendNF (sem transmitir)</span>
            <span class="badge bg-{{ $integrationEnabled ?? false ? 'success' : 'secondary' }}">{{ $integrationEnabled ?? false ? 'API configurada' : 'API não configurada' }}</span>
        </div>
        <div class="card-body">
            <p class="text-muted small">A pré-emissão consulta a SendNF sem transmitir nada. Útil para validar dados antes de emitir.</p>

            @if (! ($integrationEnabled ?? false))
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    A integração com a SendNF não está configurada (<code>SENDFN_BASE_URL</code> / <code>SENDFN_API_KEY</code>). Pré-visualizar exibirá um erro até que a API seja habilitada.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.fiscal.nfse.preview', $nfse) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="tomador_documento" class="form-label">Documento do tomador</label>
                        <input type="text" id="tomador_documento" name="tomador_documento" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="tomador_nome" class="form-label">Nome do tomador</label>
                        <input type="text" id="tomador_nome" name="tomador_nome" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="tomador_email" class="form-label">E-mail do tomador</label>
                        <input type="email" id="tomador_email" name="tomador_email" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label for="servico_descricao" class="form-label">Descrição do serviço</label>
                        <input type="text" id="servico_descricao" name="servico_descricao" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="valor_servico_cents" class="form-label">Valor (centavos)</label>
                        <input type="number" id="valor_servico_cents" name="valor_servico_cents" class="form-control" min="0" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" {{ ! ($integrationEnabled ?? false) ? 'disabled' : '' }}><i class="bi bi-eye me-1"></i>Pré-visualizar nota</button>
                    <span class="text-muted small ms-2">Somente consulta; não transmite a nota.</span>
                </div>
            </form>
        </div>
    </div>

    <!-- Emissão e cancelamento (bloqueados) -->
    <div class="card shadow-sm mb-3" style="max-width: 760px;">
        <div class="card-header bg-white fw-semibold">Emissão e cancelamento</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.fiscal.nfse.emit', $nfse) }}" class="d-inline" @if (! $canEmit) style="display:none;" @endif>
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('Confirmar emissão real da nota?')"><i class="bi bi-send me-1"></i>Emitir nota</button>
            </form>
            <form method="POST" action="{{ route('admin.fiscal.nfse.cancel', $nfse) }}" class="d-inline" @if (! $canEmit) style="display:none;" @endif>
                @csrf
                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Confirmar cancelamento?')"><i class="bi bi-trash me-1"></i>Cancelar nota</button>
            </form>
            <div class="mt-2">
                <p class="text-muted small">
                    A emissão real e o cancelamento permanecem <strong>bloqueados</strong> enquanto a integração não for habilitada, a clínica autorizada e não houver liberação explícita. Rotas mantidas apenas para futura ativação.
                </p>
            </div>
        </div>
    </div>

    <!-- Histórico de eventos -->
    <div class="card shadow-sm" style="max-width: 760px;">
        <div class="card-header bg-white fw-semibold">Histórico de eventos</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Origem</th>
                        <th>Evento externo</th>
                        <th>Processado em</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ $event->event_type }}</td>
                            <td>{{ $event->source }}</td>
                            <td class="small text-muted">{{ $event->event_id ?? '-' }}</td>
                            <td>{{ $event->processed_at?->format('d/m/Y H:i:s') ?? $event->created_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted p-3">Sem eventos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
