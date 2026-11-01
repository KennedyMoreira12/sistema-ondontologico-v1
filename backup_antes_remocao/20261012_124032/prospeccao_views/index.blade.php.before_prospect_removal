@extends('layouts.platform')

@section('title', 'Prospecção')
@section('page-title', 'Prospecção comercial')

@php
    $corGrupo = ['aberto' => 'secondary', 'ativo' => 'primary', 'ganho' => 'success', 'perdido' => 'dark'];
    $corTelefone = ['valid' => 'success', 'incomplete' => 'warning', 'unconfirmed' => 'secondary', 'invalid' => 'danger'];
    $temFiltro = $filtros['busca'] !== '' || $filtros['category'] !== '' || $filtros['city'] !== ''
        || $filtros['priority'] !== '' || $filtros['phone_status'] !== '' || $filtros['status'] !== '';
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="h4 mb-0">Prospecção comercial</h1>
        <p class="text-muted small mb-0">
            Contatos captados das listas de Januária. O acompanhamento é manual — nenhuma mensagem é enviada por aqui.
        </p>
    </div>

    @php
        $waBadge = $whatsapp['conectado']
            ? 'success'
            : ($whatsapp['status'] === 'awaiting_qr' ? 'warning text-dark' : ($whatsapp['status'] === 'error' ? 'danger' : 'secondary'));
    @endphp
    <div class="card shadow-sm mb-4" id="wa-prospeccao-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0"><i class="bi bi-whatsapp text-success me-1"></i>WhatsApp de prospecção</h2>
                <span class="badge bg-{{ $waBadge }}" id="wa-status-badge">{{ $whatsapp['status_label'] }}</span>
            </div>
            <p class="text-muted small mb-3">
                Instância Evolution dedicada só à prospecção, separada das clínicas. Nada é enviado automaticamente, e o
                QR Code só é gerado quando você clicar.
            </p>
            <dl class="row small mb-3">
                <dt class="col-sm-4 text-muted">Instância</dt>
                <dd class="col-sm-8 mb-0 font-monospace" id="wa-instance">{{ $whatsapp['instance'] }}</dd>
                <dt class="col-sm-4 text-muted">Última verificação</dt>
                <dd class="col-sm-8 mb-0" id="wa-ultima">
                    {{ ! empty($whatsapp['last_state_at']) ? \Illuminate\Support\Carbon::parse($whatsapp['last_state_at'])->format('d/m/Y H:i') : '—' }}
                </dd>
            </dl>

            @if (auth()->user()->isSuperAdmin())
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" id="wa-criar" class="btn btn-sm btn-outline-primary {{ $whatsapp['configurado'] ? 'd-none' : '' }}">
                        <i class="bi bi-plus-circle me-1"></i>Configurar instância
                    </button>
                    <button type="button" id="wa-qrcode" class="btn btn-sm btn-primary {{ $whatsapp['configurado'] ? '' : 'd-none' }}">
                        <i class="bi bi-qr-code me-1"></i>Gerar QR Code
                    </button>
                    <button type="button" id="wa-atualizar" class="btn btn-sm btn-outline-secondary {{ $whatsapp['configurado'] ? '' : 'd-none' }}">
                        <i class="bi bi-arrow-clockwise me-1"></i>Atualizar estado
                    </button>
                    <span id="wa-loading" class="text-muted small d-none align-self-center">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Consultando…
                    </span>
                </div>
            @endif

            <div id="wa-erro" class="alert alert-danger py-2 small d-none" role="alert"></div>
            <div id="wa-info" class="alert alert-info py-2 small d-none" role="alert"></div>

            <div id="wa-qr" class="text-center d-none">
                <img id="wa-qr-img" src="" alt="QR Code do WhatsApp de prospecção" class="img-fluid border rounded p-2 bg-white" style="max-width: 260px;">
                <p class="text-muted small mt-2 mb-0">
                    Abra o WhatsApp do número de prospecção → Aparelhos conectados → Conectar aparelho.
                    O código expira em cerca de 1 minuto; se expirar, clique em "Gerar QR Code" novamente.
                </p>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Total</div>
                <div class="h4 mb-0">{{ $resumo['total'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">A validar</div>
                <div class="h4 mb-0 text-warning">{{ $resumo['a_validar'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Não contatados</div>
                <div class="h4 mb-0">{{ $resumo['nao_contatado'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Em negociação</div>
                <div class="h4 mb-0 text-primary">{{ $resumo['negociacao'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Reuniões marcadas</div>
                <div class="h4 mb-0 text-info">{{ $resumo['reuniao_marcada'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Clientes</div>
                <div class="h4 mb-0 text-success">{{ $resumo['cliente'] }}</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="busca" class="form-label small text-muted">Buscar</label>
                    <input id="busca" type="search" class="form-control" name="busca" value="{{ $filtros['busca'] }}" placeholder="Nome, telefone ou endereço">
                </div>
                <div class="col-md-2">
                    <label for="category" class="form-label small text-muted">Categoria</label>
                    <select id="category" class="form-select" name="category">
                        <option value="">Todas</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria }}" @selected($filtros['category'] === $categoria)>{{ $categoria }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="city" class="form-label small text-muted">Cidade</label>
                    <select id="city" class="form-select" name="city">
                        <option value="">Todas</option>
                        @foreach ($cidades as $cidade)
                            <option value="{{ $cidade }}" @selected($filtros['city'] === $cidade)>{{ $cidade }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="priority" class="form-label small text-muted">Prioridade</label>
                    <select id="priority" class="form-select" name="priority">
                        <option value="">Todas</option>
                        @foreach ($prioridades as $chave => $rotulo)
                            <option value="{{ $chave }}" @selected($filtros['priority'] === $chave)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="phone_status" class="form-label small text-muted">Situação do telefone</label>
                    <select id="phone_status" class="form-select" name="phone_status">
                        <option value="">Todas</option>
                        @foreach ($phoneStatuses as $chave => $rotulo)
                            <option value="{{ $chave }}" @selected($filtros['phone_status'] === $chave)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label small text-muted">Etapa do funil</label>
                    <select id="status" class="form-select" name="status">
                        <option value="">Todas</option>
                        @foreach ($statuses as $chave => $meta)
                            <option value="{{ $chave }}" @selected($filtros['status'] === $chave)>{{ $meta['nome'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filtrar</button>
                    @if ($temFiltro)
                        <a href="{{ route('painel.prospeccao.index') }}" class="btn btn-outline-secondary">Limpar</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Contato</th>
                            <th>Telefone</th>
                            <th>Etapa</th>
                            <th>Prioridade</th>
                            <th>Procedência</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($prospects as $prospect)
                            <tr>
                                <td>
                                    <a href="{{ route('painel.prospeccao.show', $prospect) }}" class="fw-semibold text-decoration-none">{{ $prospect->name }}</a>
                                    <div class="text-muted small">
                                        {{ $prospect->category ?? '—' }}
                                        @if ($prospect->address)
                                            · {{ $prospect->address }}
                                        @else
                                            · {{ $prospect->city }}/{{ $prospect->state }}
                                        @endif
                                    </div>
                                </td>
                                <td class="small">
                                    <div class="font-monospace">{{ $prospect->phone_original ?? '—' }}</div>
                                    <span class="badge bg-{{ $corTelefone[$prospect->phone_status] ?? 'secondary' }}">
                                        {{ $prospect->phoneStatusLabel() }}
                                    </span>
                                    @if ($prospect->phone_status !== 'valid')
                                        <span class="badge bg-warning text-dark" title="Precisa de validação manual">
                                            <i class="bi bi-exclamation-triangle"></i> a validar
                                        </span>
                                    @endif
                                    @if ($prospect->phone_normalized && $compartilhados->contains($prospect->phone_normalized))
                                        <span class="badge bg-info text-dark" title="O mesmo número aparece em outro contato">
                                            <i class="bi bi-people"></i> compartilhado
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $corGrupo[config("prospeccao.statuses.{$prospect->status}.grupo")] ?? 'secondary' }}">
                                        {{ $prospect->statusLabel() }}
                                    </span>
                                    @if ($prospect->next_follow_up_at)
                                        <div class="text-muted small">retorno {{ $prospect->next_follow_up_at->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    <span class="badge bg-light text-dark border">{{ $prospect->priorityLabel() }}</span>
                                </td>
                                <td class="small">
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $prospect->origins_count }} origem(ns)</span>
                                    @if ($prospect->source)
                                        <div class="text-muted small">{{ $prospect->source }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('painel.prospeccao.show', $prospect) }}" class="btn btn-sm btn-outline-secondary" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Nenhum contato encontrado para os filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $prospects->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $waUrls = [
            'show' => route('painel.prospeccao.whatsapp.show'),
            'instancia' => route('painel.prospeccao.whatsapp.instancia'),
            'estado' => route('painel.prospeccao.whatsapp.estado'),
            'qrcode' => route('painel.prospeccao.whatsapp.qrcode'),
        ];
    @endphp
    <script>
        window.ljProspeccaoWhatsApp = @json($waUrls);
    </script>
    <script>
        (function () {
            const config = window.ljProspeccaoWhatsApp;
            if (! config) {
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const badge = document.getElementById('wa-status-badge');
            const instanceEl = document.getElementById('wa-instance');
            const ultimaEl = document.getElementById('wa-ultima');
            const btnCriar = document.getElementById('wa-criar');
            const btnQr = document.getElementById('wa-qrcode');
            const btnAtualizar = document.getElementById('wa-atualizar');
            const loading = document.getElementById('wa-loading');
            const erro = document.getElementById('wa-erro');
            const info = document.getElementById('wa-info');
            const qrArea = document.getElementById('wa-qr');
            const qrImg = document.getElementById('wa-qr-img');

            const cores = { connected: 'success', awaiting_qr: 'warning text-dark', error: 'danger' };

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
                if (btnCriar) { btnCriar.disabled = ativo; }
                if (btnQr) { btnQr.disabled = ativo; }
                if (btnAtualizar) { btnAtualizar.disabled = ativo; }
            }

            function formatarData(iso) {
                if (! iso) { return '—'; }
                try { return new Date(iso).toLocaleString('pt-BR'); } catch (e) { return '—'; }
            }

            function aplicar(data) {
                if (badge) {
                    badge.textContent = data.status_label || 'Desconhecida';
                    badge.className = 'badge bg-' + (cores[data.status] || 'secondary');
                }
                if (instanceEl && data.instance) {
                    instanceEl.textContent = data.instance;
                }
                if (ultimaEl && data.last_state_at) {
                    ultimaEl.textContent = formatarData(data.last_state_at);
                }

                if (qrImg && qrArea) {
                    if (data.qrcode) {
                        qrImg.src = data.qrcode;
                        qrArea.classList.remove('d-none');
                    } else {
                        qrArea.classList.add('d-none');
                    }
                }

                if (data.configurado) {
                    btnCriar?.classList.add('d-none');
                    btnQr?.classList.remove('d-none');
                    btnAtualizar?.classList.remove('d-none');
                }

                if (data.mensagem) {
                    mostrarInfo(data.mensagem);
                }
            }

            async function pedir(url) {
                const resposta = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                });

                let data = {};
                try { data = await resposta.json(); } catch (e) { data = {}; }

                if (! resposta.ok) {
                    throw new Error(data.mensagem || 'Não foi possível completar a ação.');
                }

                return data;
            }

            async function acao(url, sucesso) {
                limpar();
                carregando(true);
                try {
                    const data = await pedir(url);
                    aplicar(data);
                    if (sucesso) { mostrarInfo(sucesso); }
                } catch (e) {
                    mostrarErro(e.message);
                } finally {
                    carregando(false);
                }
            }

            btnCriar?.addEventListener('click', () => acao(config.instancia, 'Instância de prospecção configurada.'));
            btnAtualizar?.addEventListener('click', () => acao(config.estado));
            btnQr?.addEventListener('click', () => acao(config.qrcode));
        })();
    </script>
@endpush
