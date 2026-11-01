@extends('layouts.platform')

@section('title', $clinica->name)
@section('page-title', $clinica->name)

@section('content')
    @if (session('senha_provisoria'))
        <div class="alert alert-success">
            <div class="fw-semibold mb-1">Clínica criada.</div>
            <div class="mb-2">Senha do administrador — aparece só agora, e não é gravada em log:</div>
            <code class="fs-6">{{ session('senha_provisoria') }}</code>
        </div>
    @endif

    @if (session('token'))
        <div class="alert alert-success">
            <div class="fw-semibold mb-1">Token gerado — aparece só agora:</div>
            <code class="fs-6">{{ session('token') }}</code>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0">{{ $clinica->name }}</h1>
            <p class="text-muted small mb-0">
                {{ $clinica->email }}
                @if ($clinica->document) — {{ $clinica->document }} @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            @if (auth()->user()->isSuperAdmin())
                <form method="POST" action="{{ route('painel.clinicas.migrate', $clinica) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-database-up me-1"></i>Atualizar schema
                    </button>
                </form>

                <form method="POST" action="{{ route('painel.clinicas.conexao', $clinica) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-outline-{{ $clinica->tenantConnection?->is_active ? 'danger' : 'success' }}">
                        <i class="bi bi-plug me-1"></i>
                        {{ $clinica->tenantConnection?->is_active ? 'Desligar conexão' : 'Religar conexão' }}
                    </button>
                </form>
            @endif

            <a href="{{ route('painel.clinicas.edit', $clinica) }}" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i>Editar
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3" id="wa-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0">WhatsApp</h2>
                        <span class="badge bg-secondary" id="wa-status-badge">Carregando…</span>
                    </div>

                    <dl class="row mb-3 small">
                        <dt class="col-4 text-muted">Número</dt>
                        <dd class="col-8 text-end mb-0" id="wa-numero">{{ $clinica->whatsapp ?: '—' }}</dd>
                    </dl>

                    @if (auth()->user()->isSuperAdmin())
                        <form id="wa-form" class="row g-2 align-items-end mb-3">
                            <div class="col-md-8">
                                <label for="wa-numero-input" class="form-label small text-muted">Número do WhatsApp (DDI + DDD)</label>
                                <input id="wa-numero-input" type="text" inputmode="numeric" class="form-control form-control-sm"
                                       name="numero" placeholder="5511999999999" value="{{ $clinica->whatsapp }}">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" id="wa-gerar" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-qr-code me-1"></i>Gerar QR Code
                                </button>
                            </div>
                        </form>
                    @endif

                    <div class="d-flex align-items-center gap-2 mb-3">
                        <button type="button" id="wa-atualizar" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-clockwise me-1"></i>Atualizar estado
                        </button>
                        <span id="wa-loading" class="text-muted small d-none">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Consultando…
                        </span>
                    </div>

                    <div id="wa-erro" class="alert alert-danger py-2 small d-none" role="alert"></div>
                    <div id="wa-info" class="alert alert-info py-2 small d-none" role="alert"></div>

                    <div id="wa-qr" class="text-center d-none">
                        <img id="wa-qr-img" src="" alt="QR Code do WhatsApp" class="img-fluid border rounded p-2 bg-white" style="max-width: 260px;">
                        <p class="text-muted small mt-2 mb-0">Abra o WhatsApp da clínica → Aparelhos conectados → Conectar aparelho.</p>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Assinatura</h2>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Plano</th>
                                    <th>Estado</th>
                                    <th>Período</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($assinaturas as $assinatura)
                                    <tr>
                                        <td>{{ $assinatura->plan?->name ?? '—' }}</td>
                                        <td><span class="badge bg-secondary">{{ $assinatura->statusLabel() }}</span></td>
                                        <td class="text-muted small">
                                            {{ $assinatura->current_period_start?->format('d/m/Y') ?? '—' }}
                                            a
                                            {{ $assinatura->current_period_end?->format('d/m/Y') ?? '—' }}
                                            @if ($assinatura->isOnGracePeriod())
                                                <span class="badge bg-warning text-dark">carência</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if (auth()->user()->isSuperAdmin())
                                                <form method="POST" action="{{ route('painel.assinaturas.renovar', $assinatura) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Renovar por mais um mês">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Nenhuma assinatura registrada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (auth()->user()->isSuperAdmin())
                        <form method="POST" action="{{ route('painel.assinaturas.store', $clinica) }}" class="row g-2 align-items-end mt-3 pt-3 border-top">
                            @csrf
                            <div class="col-md-5">
                                <label for="plan_id" class="form-label small text-muted">Assinar plano</label>
                                <select id="plan_id" class="form-select form-select-sm" name="plan_id" required>
                                    <option value="">Selecione...</option>
                                    @foreach ($planos as $plano)
                                        <option value="{{ $plano->id }}">{{ $plano->name }} — {{ $plano->monthlyPrice() }}/mês</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="current_period_end" class="form-label small text-muted">Vence em</label>
                                <input id="current_period_end" type="date" class="form-control form-control-sm" name="current_period_end" value="{{ now()->addMonth()->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-sm btn-primary w-100">Assinar</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Tokens de integração</h2>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Integração</th>
                                    <th>Permissões</th>
                                    <th>Hash</th>
                                    <th>Último uso</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($clinica->integrationTokens as $token)
                                    <tr>
                                        <td>{{ $token->name }}</td>
                                        <td class="small">
                                            @forelse ($token->abilities ?? [] as $ability)
                                                <span class="badge bg-light text-dark border me-1">
                                                    {{ \App\Support\IntegrationAbilities::labels()[$ability] ?? $ability }}
                                                </span>
                                            @empty
                                                {{-- Token anterior à exigência de abilities: nasce sem nenhuma. --}}
                                                <span class="badge bg-warning text-dark">sem permissões</span>
                                            @endforelse
                                        </td>
                                        <td class="text-muted small font-monospace">{{ $token->maskedHint() }}</td>
                                        <td class="text-muted small">{{ $token->last_used_at?->format('d/m/Y H:i') ?? 'nunca' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $token->isActive() ? 'success' : 'secondary' }}">
                                                {{ $token->isActive() ? 'ativo' : 'revogado' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Nenhum token gerado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (auth()->user()->isSuperAdmin())
                        <form method="POST" action="{{ route('painel.clinicas.tokens.store', $clinica) }}" class="mt-3 pt-3 border-top">
                            @csrf

                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label for="name" class="form-label small text-muted">Nova integração</label>
                                    <input id="name" type="text" class="form-control form-control-sm" name="name" placeholder="n8n, webhook da Meta..." required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">Gerar token</button>
                                </div>
                            </div>

                            {{--
                                Sem nenhuma marcada o token nasce morto: `hasAbility()`
                                recusa token sem abilities. O menor privilege útil é
                                `appointments.view`, então é o que vem marcado.
                            --}}
                            <fieldset class="mt-3">
                                <legend class="form-label small text-muted mb-1">Permissões deste token</legend>
                                <div class="row g-1">
                                    @foreach (\App\Support\IntegrationAbilities::labels() as $ability => $label)
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="abilities[]"
                                                    value="{{ $ability }}"
                                                    id="ability-{{ $loop->index }}"
                                                    @checked($ability === 'appointments.view')
                                                >
                                                <label class="form-check-label small" for="ability-{{ $loop->index }}">
                                                    {{ $label }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @error('abilities')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                                @error('abilities.*')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </fieldset>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Ações do painel</h2>

                    <ul class="list-unstyled mb-0">
                        @forelse ($acoes as $acao)
                            <li class="d-flex justify-content-between align-items-start py-2 border-bottom">
                                <div>
                                    <div class="fw-semibold">{{ $acao->action }}</div>
                                    <div class="text-muted small">{{ $acao->adminUser?->name ?? 'sistema' }}</div>
                                </div>
                                <div class="text-muted small text-nowrap">{{ $acao->created_at?->format('d/m/Y H:i') }}</div>
                            </li>
                        @empty
                            <li class="text-muted small py-3">Nenhuma ação registrada para esta clínica.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Estado</h2>

                    <dl class="row mb-0 small">
                        <dt class="col-6 text-muted">Clínica</dt>
                        <dd class="col-6 text-end">
                            <span class="badge bg-{{ $clinica->isAccessible() ? 'success' : 'secondary' }}">
                                {{ $estados[$clinica->status] ?? $clinica->status }}
                            </span>
                        </dd>

                        <dt class="col-6 text-muted">Ativa</dt>
                        <dd class="col-6 text-end">{{ $clinica->is_active ? 'sim' : 'não' }}</dd>

                        @if ($clinica->isOnTrial())
                            <dt class="col-6 text-muted">Avaliação até</dt>
                            <dd class="col-6 text-end">{{ $clinica->trial_ends_at?->format('d/m/Y') }}</dd>
                        @endif

                        <dt class="col-6 text-muted">Fuso</dt>
                        <dd class="col-6 text-end">{{ $clinica->timezone }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Banco de dados</h2>

                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Driver</dt>
                        <dd class="col-7 text-end font-monospace">{{ $clinica->tenantConnection?->driver ?? '—' }}</dd>

                        <dt class="col-5 text-muted">Nome</dt>
                        <dd class="col-7 text-end font-monospace text-break">{{ $clinica->tenantConnection?->database_name ?? '—' }}</dd>

                        <dt class="col-5 text-muted">Host</dt>
                        <dd class="col-7 text-end font-monospace">{{ $clinica->tenantConnection?->database_host ?? '—' }}</dd>

                        <dt class="col-5 text-muted">Conexão</dt>
                        <dd class="col-7 text-end">
                            <span class="badge bg-{{ ($clinica->tenantConnection?->is_active ?? false) ? 'success' : 'danger' }}">
                                {{ ($clinica->tenantConnection?->is_active ?? false) ? 'ativa' : 'desligada' }}
                            </span>
                        </dd>

                        <dt class="col-5 text-muted">Schema em</dt>
                        <dd class="col-7 text-end">{{ $clinica->tenantConnection?->last_migrated_at?->format('d/m/Y H:i') ?? 'nunca' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Usuários ({{ $clinica->users->count() }})</h2>

                    <ul class="list-group list-group-flush">
                        @forelse ($clinica->users as $usuario)
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span>{{ $usuario->name }}</span>
                                <span class="text-muted small">{{ $usuario->is_active ? 'ativo' : 'inativo' }}</span>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-muted small">Nenhum usuário além do administrador.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            @if (auth()->user()->isSuperAdmin())
                <div class="card border-danger shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 text-danger mb-2">Excluir clínica</h2>
                        <p class="text-muted small">
                            Desativa a clínica, desliga a conexão e revoga os tokens. O banco de dados é
                            preservado, a menos que você marque a opção abaixo.
                        </p>

                        <form method="POST" action="{{ route('painel.clinicas.destroy', $clinica) }}"
                              onsubmit="return confirm('Excluir {{ $clinica->name }}?')">
                            @csrf
                            @method('DELETE')

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="descartar_banco" value="1" id="descartar_banco">
                                <label class="form-check-label small" for="descartar_banco">
                                    Apagar também o banco de dados (irreversível)
                                </label>
                            </div>

                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Excluir clínica</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $whatsappConfig = [
            'statusUrl' => route('painel.clinicas.whatsapp.show', $clinica),
            'qrcodeUrl' => route('painel.clinicas.whatsapp.qrcode', $clinica),
            'podeGerar' => auth()->user()->isSuperAdmin(),
        ];
    @endphp
    <script>
        window.ljWhatsappConfig = @json($whatsappConfig);
    </script>
    <script>
        (function () {
            const config = window.ljWhatsappConfig;
            if (! config) {
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const badge = document.getElementById('wa-status-badge');
            const numeroEl = document.getElementById('wa-numero');
            const form = document.getElementById('wa-form');
            const input = document.getElementById('wa-numero-input');
            const btnGerar = document.getElementById('wa-gerar');
            const btnAtualizar = document.getElementById('wa-atualizar');
            const loading = document.getElementById('wa-loading');
            const erro = document.getElementById('wa-erro');
            const info = document.getElementById('wa-info');
            const qrArea = document.getElementById('wa-qr');
            const qrImg = document.getElementById('wa-qr-img');

            const rotulos = { open: 'Conectado', connecting: 'Aguardando leitura', close: 'Desconectado' };

            function limpar() {
                erro?.classList.add('d-none');
                info?.classList.add('d-none');
            }

            function mostrarErro(mensagem) {
                info?.classList.add('d-none');
                if (erro) {
                    erro.textContent = mensagem;
                    erro.classList.remove('d-none');
                }
            }

            function mostrarInfo(mensagem) {
                erro?.classList.add('d-none');
                if (info) {
                    info.textContent = mensagem;
                    info.classList.remove('d-none');
                }
            }

            function carregando(ativo) {
                loading?.classList.toggle('d-none', ! ativo);
                if (btnGerar) { btnGerar.disabled = ativo; }
                if (btnAtualizar) { btnAtualizar.disabled = ativo; }
            }

            function aplicarEstado(data) {
                const estado = data.estado || null;
                const conectado = data.conectado === true || estado === 'open';

                if (badge) {
                    badge.textContent = estado ? (rotulos[estado] || 'Desconhecido') : 'Não configurado';
                    badge.className = 'badge bg-' + (conectado ? 'success' : (estado === 'connecting' ? 'warning text-dark' : 'secondary'));
                }

                if (numeroEl) {
                    numeroEl.textContent = data.numero || '—';
                }

                if (data.qrcode && qrImg && qrArea) {
                    qrImg.src = data.qrcode;
                    qrArea.classList.remove('d-none');
                } else {
                    qrArea?.classList.add('d-none');
                }

                if (data.mensagem) {
                    mostrarInfo(data.mensagem);
                }
            }

            async function pedir(url, opcoes = {}) {
                const resposta = await fetch(url, {
                    method: opcoes.method || 'GET',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: opcoes.body ? JSON.stringify(opcoes.body) : undefined,
                    credentials: 'same-origin',
                });

                let data = {};
                try {
                    data = await resposta.json();
                } catch (e) {
                    data = {};
                }

                if (! resposta.ok) {
                    throw new Error(data.mensagem || 'Não foi possível completar a ação.');
                }

                return data;
            }

            async function atualizar() {
                carregando(true);
                limpar();
                qrArea?.classList.add('d-none');

                try {
                    aplicarEstado(await pedir(config.statusUrl));
                } catch (e) {
                    mostrarErro(e.message);
                } finally {
                    carregando(false);
                }
            }

            async function gerar(evento) {
                evento?.preventDefault();
                carregando(true);
                limpar();
                qrArea?.classList.add('d-none');

                try {
                    const data = await pedir(config.qrcodeUrl, {
                        method: 'POST',
                        body: { numero: input ? (input.value.trim() || null) : null },
                    });

                    aplicarEstado(data);

                    if (data.qrcode) {
                        mostrarInfo('Escaneie o QR Code no WhatsApp da clínica para conectar.');
                    }
                } catch (e) {
                    mostrarErro(e.message);
                } finally {
                    carregando(false);
                }
            }

            btnAtualizar?.addEventListener('click', atualizar);
            form?.addEventListener('submit', gerar);

            atualizar();
        })();
    </script>
@endpush
