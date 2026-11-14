{{--
    Configuração do emissor SendNF e rascunhos recentes de NFS-e.

    Usada pela tela unificada de Notas fiscais (`fiscal.index`) e pela rota
    administrativa antiga (`admin.fiscal.index`). Espera as variáveis
    `$perfil`, `$company`, `$drafts`, `$canEmit` e `$integrationEnabled`.
--}}

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

<!-- Dados de identificação da clínica (já cadastrados) -->
<div class="card shadow-sm mb-3" style="max-width: 760px;">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-2"></i>Identificação da clínica</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Razão social</label>
                <p class="form-control-plaintext">{{ $perfil->legal_name ?? 'Não informado' }}</p>
            </div>
            <div class="col-md-6">
                <label class="form-label">CNPJ</label>
                <p class="form-control-plaintext">{{ $perfil->document ?? 'Não informado' }}</p>
            </div>
        </div>
    </div>
</div>

@if (! $integrationEnabled)
    <div class="alert alert-warning mb-3" style="max-width: 760px;">
        <i class="bi bi-info-circle me-1"></i>
        A integração com a SendNF está <strong>desabilitada</strong> (<code>SENDFN_ENABLED=false</code>). Nenhuma nota fiscal será transmitida. Habilite a integração e autorize a clínica antes de emitir.
    </div>
@endif

<!-- Configuração fiscal -->
<div class="card shadow-sm mb-3" style="max-width: 760px;">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-gear me-2"></i>Configuração de emissão</span>
        <span class="badge bg-{{ $company->enabled ? 'success' : 'secondary' }}">{{ $company->enabled ? 'Ativa localmente' : 'Desativada localmente' }}</span>
    </div>
    <div class="card-body">
        @can('fiscal.update')
            <form method="POST" action="{{ route('admin.fiscal.config.update') }}">
                @csrf
                @method('PUT')

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="enabled" class="form-label">Habilitar módulo fiscal nesta clínica</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" @checked($company->enabled) @disabled(! $integrationEnabled)>
                            <label class="form-check-label" for="enabled">Permitir pré-emissão e visualização de notas</label>
                            @if (! $integrationEnabled)
                                <div class="form-text">Desabilitado globalmente (SENDFN_ENABLED=false).</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="cnpj" class="form-label">CNPJ da empresa emitente</label>
                        <input type="text" id="cnpj" name="cnpj" value="{{ old('cnpj', $company->cnpj ?? '') }}" class="form-control" placeholder="00.000.000/0000-00">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" {{ ! $integrationEnabled ? 'disabled' : '' }}><i class="bi bi-check-lg me-1"></i>Salvar configuração</button>
                    </div>
                </div>
            </form>
        @endcan

        <div class="mt-4">
            <p class="text-muted small">Empresa na SendNF: <strong>{{ $company->sendnf_company_id ?? 'Não cadastrada' }}</strong></p>
            <p class="text-muted small">Status: <span class="badge bg-{{ $company->status === 'ready' ? 'success' : 'warning' }}">{{ ucfirst($company->status ?? 'not_configured') }}</span></p>
            <p class="text-muted small">Última consulta de readiness: {{ $company->last_check_at?->format('d/m/Y H:i:s') ?? 'Nunca' }}</p>

            @can('fiscal.update')
                <form method="POST" action="{{ route('admin.fiscal.register') }}" class="d-inline" @if (! $integrationEnabled || filled($company->sendnf_company_id)) style="display:none;" @endif>
                    @csrf
                    <input type="hidden" name="cnpj" value="{{ old('cnpj', $company->cnpj ?? '') }}">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-circle me-1"></i>Cadastrar empresa na SendNF</button>
                </form>
            @endcan

            <form method="POST" action="{{ route('admin.fiscal.readiness') }}" class="d-inline" @if (! filled($company->sendnf_company_id)) style="display:none;" @endif>
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary" {{ ! $integrationEnabled ? 'disabled' : '' }}><i class="bi bi-arrow-clockwise me-1"></i>Consultar readiness</button>
            </form>

            @if (! $company->enabled && ! $integrationEnabled)
                <div class="mt-2 form-text">A emissão real permanece bloqueada enquanto a integração não for habilitada e a clínica autorizada.</div>
            @endif
        </div>
    </div>
</div>

<!-- Rascunhos recentes -->
<div class="card shadow-sm mb-3" style="max-width: 760px;">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-earmark-text me-2"></i>Rascunhos recentes</span>
        <a href="{{ route('admin.fiscal.nfse.index') }}" class="btn btn-sm btn-outline-secondary">Ver todas</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Tomador</th>
                    <th>Valor</th>
                    <th>Chave de idempotência</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($drafts as $nfse)
                    <tr>
                        <td>{{ $nfse->id }}</td>
                        <td><span class="badge bg-{{ $nfse->status === 'authorized' ? 'success' : ($nfse->status === 'rejected' ? 'danger' : 'secondary') }}">{{ ucfirst($nfse->status ?? 'draft') }}</span></td>
                        <td>{{ $nfse->tomador_nome ?? '-' }}</td>
                        <td>R$ {{ number_format($nfse->valor_servico ?? 0, 2, ',', '.') }}</td>
                        <td class="small text-muted">{{ $nfse->idempotency_key ?? '-' }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.fiscal.nfse.show', $nfse) }}" class="btn btn-sm btn-outline-secondary">Detalhes</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted p-3">Nenhum rascunho de nota fiscal.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
