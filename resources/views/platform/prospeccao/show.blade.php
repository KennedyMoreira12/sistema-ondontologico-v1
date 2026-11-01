@extends('layouts.platform')

@section('title', $prospect->name)
@section('page-title', 'Prospecção comercial')

@php
    $corGrupo = ['aberto' => 'secondary', 'ativo' => 'primary', 'ganho' => 'success', 'perdido' => 'dark'];
    $corTelefone = ['valid' => 'success', 'incomplete' => 'warning', 'unconfirmed' => 'secondary', 'invalid' => 'danger'];
    $rotuloStatus = fn (?string $chave): string => $chave === null ? '—' : (config("prospeccao.statuses.{$chave}.nome") ?? $chave);
    $wa = $prospect->whatsappUrl();
@endphp

@section('content')
    <div class="mb-3">
        <a href="{{ route('painel.prospeccao.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Voltar para a listagem</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h1 class="h4 mb-1">{{ $prospect->name }}</h1>
                    <div class="text-muted small">
                        {{ $prospect->category ?? 'Sem categoria' }}
                        @if ($prospect->specialty) · {{ $prospect->specialty }} @endif
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-{{ $corGrupo[config("prospeccao.statuses.{$prospect->status}.grupo")] ?? 'secondary' }}">{{ $prospect->statusLabel() }}</span>
                    <span class="badge bg-light text-dark border">{{ $prospect->priorityLabel() }}</span>
                    @if ($prospect->validated_at)
                        <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check2"></i> validado</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Atualizar oportunidade</h2>

                    <form method="POST" action="{{ route('painel.prospeccao.update', $prospect) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="status" class="form-label small text-muted">Etapa do funil</label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach ($statuses as $chave => $meta)
                                    <option value="{{ $chave }}" @selected(old('status', $prospect->status) === $chave)>{{ $meta['nome'] }}</option>
                                @endforeach
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label for="priority" class="form-label small text-muted">Prioridade</label>
                                <select id="priority" name="priority" class="form-select">
                                    @foreach ($prioridades as $chave => $rotulo)
                                        <option value="{{ $chave }}" @selected(old('priority', $prospect->priority) === $chave)>{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="channel" class="form-label small text-muted">Canal</label>
                                <select id="channel" name="channel" class="form-select">
                                    @foreach ($canais as $chave => $rotulo)
                                        <option value="{{ $chave }}" @selected(old('channel', 'telefone') === $chave)>{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label for="next_follow_up_at" class="form-label small text-muted">Data sugerida para retorno</label>
                            <input id="next_follow_up_at" type="date" name="next_follow_up_at" class="form-control @error('next_follow_up_at') is-invalid @enderror"
                                   value="{{ old('next_follow_up_at', $prospect->next_follow_up_at?->format('Y-m-d')) }}">
                            @error('next_follow_up_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="result" class="form-label small text-muted">Resultado do contato</label>
                            <input id="result" type="text" name="result" class="form-control @error('result') is-invalid @enderror"
                                   value="{{ old('result') }}" maxlength="255" placeholder="Ex.: atendeu, pediu proposta">
                            @error('result') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label small text-muted">Observação comercial</label>
                            <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" maxlength="2000">{{ old('notes', $prospect->notes) }}</textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="disinterest_reason" class="form-label small text-muted">Motivo (se sem interesse)</label>
                            <textarea id="disinterest_reason" name="disinterest_reason" class="form-control @error('disinterest_reason') is-invalid @enderror" rows="2" maxlength="1000">{{ old('disinterest_reason', $prospect->disinterest_reason) }}</textarea>
                            @error('disinterest_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="validated" name="validated" @checked(old('validated', (bool) $prospect->validated_at))>
                            <label class="form-check-label small" for="validated">Dados revisados/validados</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Salvar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 mb-3">Procedência</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Documento</th>
                                    <th>Referência</th>
                                    <th class="text-end">PDF</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($prospect->origins as $origin)
                                    <tr>
                                        <td class="small text-truncate" style="max-width: 220px;">{{ $origin->source_file ?? $origin->source }}</td>
                                        <td class="small text-muted">#{{ $origin->source_ref ?? '—' }}</td>
                                        <td class="text-end">
                                            @if (array_key_exists($origin->source, config('prospeccao.source_documents', [])))
                                                <a href="{{ route('painel.prospeccao.origem', $origin->source) }}" class="btn btn-sm btn-outline-secondary" title="Baixar PDF de origem">
                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                </a>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted small">Sem procedência registrada.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Histórico</h2>
                    <ul class="list-unstyled mb-0" style="max-height: 520px; overflow-y: auto;">
                        @forelse ($prospect->interactions as $interacao)
                            <li class="border-bottom py-2" style="min-width: 0;">
                                <div class="d-flex justify-content-between flex-wrap gap-1">
                                    <span class="small fw-semibold text-break">
                                        {{ $rotuloStatus($interacao->status_before) }}
                                        <i class="bi bi-arrow-right"></i>
                                        {{ $rotuloStatus($interacao->status_after) }}
                                    </span>
                                    <span class="text-muted small">{{ $interacao->interacted_at?->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="text-muted small text-break">
                                    {{ $interacao->channelLabel() }}
                                    · {{ $interacao->adminUser?->name ?? 'sistema' }}
                                    @if ($interacao->result) · {{ $interacao->result }} @endif
                                </div>
                                @if ($interacao->notes)
                                    <div class="small text-break">{{ $interacao->notes }}</div>
                                @endif
                            </li>
                        @empty
                            <li class="text-muted small">Nenhuma movimentação registrada ainda.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Área de Mensagem Comercial --}}
    @if ($podeEnviarComercial || $botStatus !== 'Ativo' || $estaBloqueado)
    <div class="card shadow-sm mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Mensagem Comercial</h2>
            <small>{{ $botStatus }} {{ $estaBloqueado ? '(bloqueado)' : '' }}</small>
        </div>
        <div class="card-body p-3">
            @if ($estaBloqueado)
                <div class="alert alert-warning mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Este contato solicitou não receber mensagens. Marcado como <strong>opted_out</strong>. Nenhum envio comercial será realizado enquanto mantiver esse status.
                </div>
            @endif

            @if ($botStatus === 'Pausado')
                <div class="alert alert-secondary mb-3" role="alert">
                    <i class="bi bi-pause-circle me-1"></i>
                    O bot de prospecção está <strong>pausado</strong> para este contato. Nenhuma mensagem será enviada enquanto permanecer pausado.
                </div>
            @endif

            {{-- Formulário de mensagem comercial --}}
            @if ($podeEnviarComercial)
                <form id="formComercial" method="POST" action="{{ route('painel.prospeccao.comercial') }}">
                    @csrf
                    @method('POST')

                    <input type="hidden" name="prospect_id" value="{{ $prospect->id }}">
                    <input type="hidden" name="acao" value="preparar">
                    {{-- Corpo aprovado na prévia; o servidor confere antes de enviar. --}}
                    <input type="hidden" name="corpo_aprovado" id="corpoAprovado" value="">

                    <div class="mb-3">
                        <label class="form-label small text-muted">Selecionar template</label>
                        <select name="template_id" class="form-select form-select-sm" id="templateSelect">
                            <option value="">Nenhum (personalizado)</option>
                            @foreach ($templates as $t)
                                <option value="{{ $t->id }}" @selected(old('template_id') == $t->id)>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Mensagem</label>
                        <textarea name="mensagem" class="form-control form-control-sm" rows="3" placeholder="Digite a mensagem para este prospect"></textarea>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">
                            Placeholders disponíveis: <code>{nome}</code>, <code>{categoria}</code>, <code>{cidade}</code>, <code>{remetente}</code>
                        </small>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnPreparar">
                        <i class="bi bi-eye me-1"></i> Preparar preview
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="btnEnviar" disabled>
                        <i class="bi bi-send me-1"></i> Aprovar e enviar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPausarBot">
                        <i class="bi bi-pause-circle me-1"></i> Pausar bot
                    </button>
                    <span id="comercialLoading" class="text-muted small ms-2 d-none">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Consultando…
                    </span>
                    @if ($prospect->estaPausadoBot())
                        <span class="text-muted small">Bot pausado em {{ $prospect->bot_paused_at?->format('d/m/Y H:i') }}</span>
                    @endif
                    @if ($prospect->estaBloqueado())
                        <span class="text-muted small">Contato blocked (opted_out)</span>
                    @endif
                </form>
            @else
                <div class="alert alert-info mb-3" role="alert">
                    <i class="bi bi-info-circle me-1"></i>
                    Não é possível enviar mensagem comercial a este prospect no momento. Verifique se o bot não está pausado e se o contato não está bloqueado.
                </div>
            @endif

            {{-- Feedback de validação/erro (preenchido via AJAX) --}}
            <div id="comercialFeedback" class="alert py-2 small mt-3 d-none" role="alert"></div>

            {{-- Área de preview: sempre no DOM (oculta) para o JS preencher --}}
            @if ($podeEnviarComercial)
                <div id="previewArea" class="mt-3 d-none">
                    <h4 class="h6 mt-3">Preview da mensagem</h4>
                    <div class="alert alert-light p-3 mb-2">
                        <p class="mb-1"><strong>Prospect:</strong> <span id="previewProspect">{{ $prospect->name }}</span></p>
                        <p class="mb-1"><strong>Telefone:</strong> <span id="previewTelefone">{{ $prospect->phone_original ?? $prospect->phone_normalized ?? '—' }}</span></p>
                        <p class="mb-1"><strong>Template:</strong> <span id="previewTemplate">Personalizado</span></p>
                        <p class="mb-1"><strong>Conteúdo:</strong></p>
                        <pre id="previewText" class="mb-0" style="white-space: pre-wrap;"></pre>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmarDestino">
                        <label class="form-check-label small" for="confirmarDestino">Confirmo que o destinatário acima está correto</label>
                    </div>
                </div>
            @endif
        </div>
    </div>
    {{-- Fim da Área de Mensagem Comercial --}}
@endif

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('formComercial');
            if (!form) {
                return;
            }

            const acaoInput = form.querySelector('input[name="acao"]');
            const templateSelect = document.getElementById('templateSelect');
            const mensagem = form.querySelector('textarea[name="mensagem"]');
            const btnPreparar = document.getElementById('btnPreparar');
            const btnEnviar = document.getElementById('btnEnviar');
            const btnPausar = document.getElementById('btnPausarBot');
            const previewArea = document.getElementById('previewArea');
            const previewText = document.getElementById('previewText');
            const previewProspect = document.getElementById('previewProspect');
            const previewTelefone = document.getElementById('previewTelefone');
            const previewTemplate = document.getElementById('previewTemplate');
            const confirmarDestino = document.getElementById('confirmarDestino');
            const corpoAprovado = document.getElementById('corpoAprovado');
            const feedback = document.getElementById('comercialFeedback');
            const loading = document.getElementById('comercialLoading');

            // Estado do fluxo de envio.
            let destinatarioValido = false;
            let enviando = false;

            const mostrarFeedback = function (tipo, texto) {
                if (!feedback) return;
                feedback.className = 'alert alert-' + tipo + ' py-2 small mt-3';
                feedback.textContent = texto;
                feedback.classList.remove('d-none');
            };

            const limparFeedback = function () {
                if (!feedback) return;
                feedback.classList.add('d-none');
                feedback.textContent = '';
            };

            const definirLoading = function (ativo) {
                if (loading) loading.classList.toggle('d-none', !ativo);
            };

            const previewPronto = function () {
                return !!previewArea && !previewArea.classList.contains('d-none');
            };

            const atualizarBotaoEnviar = function () {
                if (!btnEnviar) return;
                const confirmado = !!confirmarDestino && confirmarDestino.checked;
                btnEnviar.disabled = !(previewPronto() && destinatarioValido && confirmado) || enviando;
            };

            const limparPreview = function () {
                if (previewArea) previewArea.classList.add('d-none');
                if (confirmarDestino) confirmarDestino.checked = false;
                if (corpoAprovado) corpoAprovado.value = '';
                destinatarioValido = false;
                atualizarBotaoEnviar();
            };

            // POST via fetch; o CSRF vem no FormData e o servidor responde em JSON.
            const requisitar = async function (acao) {
                acaoInput.value = acao;
                const resposta = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                });
                const dados = await resposta.json().catch(function () { return {}; });
                return { ok: resposta.ok, statusHttp: resposta.status, dados: dados };
            };

            // 1) Preparar preview — valida e monta a prévia, SEM enviar nada.
            const prepararPreview = async function () {
                limparFeedback();

                const templateId = templateSelect ? templateSelect.value : '';
                const texto = mensagem ? mensagem.value.trim() : '';

                if (!templateId && texto === '') {
                    mostrarFeedback('danger', 'Selecione um modelo de mensagem ou escreva uma mensagem.');
                    return;
                }

                limparPreview();
                if (btnPreparar) btnPreparar.disabled = true;
                definirLoading(true);

                try {
                    const r = await requisitar('preparar');

                    if (!r.ok) {
                        if (r.statusHttp === 422 && r.dados.errors) {
                            const primeira = Object.values(r.dados.errors)[0];
                            mostrarFeedback('danger', Array.isArray(primeira) ? primeira[0] : String(primeira));
                        } else {
                            mostrarFeedback('danger', r.dados.motivo || r.dados.message || 'Não foi possível preparar a prévia.');
                        }
                        return;
                    }

                    if (r.dados.status === 'preparado') {
                        if (previewProspect) previewProspect.textContent = r.dados.prospect_nome || '—';
                        if (previewTelefone) previewTelefone.textContent = r.dados.telefone || '—';
                        if (previewTemplate) previewTemplate.textContent = r.dados.template_nome || 'Personalizado';
                        if (previewText) previewText.textContent = r.dados.corpo || '';
                        if (corpoAprovado) corpoAprovado.value = r.dados.corpo || '';
                        destinatarioValido = r.dados.destinatario_valido === true;
                        if (confirmarDestino) confirmarDestino.checked = false;
                        if (previewArea) previewArea.classList.remove('d-none');
                        atualizarBotaoEnviar();
                        mostrarFeedback('success', 'Prévia pronta. Confira o destinatário, marque a confirmação e clique em "Aprovar e enviar".');
                    } else {
                        mostrarFeedback('danger', r.dados.motivo || 'Não foi possível preparar a prévia.');
                    }
                } catch (erro) {
                    mostrarFeedback('danger', 'Erro de comunicação com o servidor. Tente novamente.');
                } finally {
                    definirLoading(false);
                    if (btnPreparar) btnPreparar.disabled = false;
                }
            };

            // 2) Aprovar e enviar — só libera após prévia válida + confirmação do destinatário.
            const aprovarEnviar = async function () {
                // Cliques repetidos enquanto o envio está em andamento são ignorados.
                if (enviando) {
                    return;
                }
                limparFeedback();

                if (!previewPronto()) {
                    mostrarFeedback('danger', 'Prepare a prévia antes de enviar.');
                    return;
                }
                if (!destinatarioValido) {
                    mostrarFeedback('danger', 'O destinatário não é um número válido para envio.');
                    return;
                }
                if (!confirmarDestino || !confirmarDestino.checked) {
                    mostrarFeedback('danger', 'Confirme que o destinatário está correto antes de enviar.');
                    return;
                }

                enviando = true;
                if (btnEnviar) btnEnviar.disabled = true;
                definirLoading(true);

                let enviado = false;

                try {
                    const r = await requisitar('aprovar_enviar');

                    if (r.ok && r.dados.status === 'enviado') {
                        enviado = true;
                        mostrarFeedback('success', 'Mensagem enviada para ' + (r.dados.telefone || 'o contato') + '. Atualizando o histórico…');
                        setTimeout(function () { window.location.reload(); }, 1200);
                        return;
                    }

                    // Prévia divergente do conteúdo atual: obriga a gerar de novo.
                    if (r.dados.status === 'desatualizado') {
                        limparPreview();
                    }

                    mostrarFeedback('danger', r.dados.motivo || r.dados.message || 'Não foi possível enviar a mensagem.');
                } catch (erro) {
                    mostrarFeedback('danger', 'Erro de comunicação com o servidor. Tente novamente.');
                } finally {
                    definirLoading(false);
                    acaoInput.value = 'preparar';
                    enviando = false;
                    // Após enviar, mantém bloqueado (a página recarrega). Em erro, reavalia o estado.
                    if (enviado) {
                        if (btnEnviar) btnEnviar.disabled = true;
                    } else {
                        atualizarBotaoEnviar();
                    }
                }
            };

            // 3) Pausar bot de prospecção para este contato.
            const pausarBot = async function () {
                limparFeedback();
                if (!window.confirm('Pausar o bot de prospecção para este contato?')) {
                    return;
                }

                definirLoading(true);

                try {
                    const r = await requisitar('pausar_bot');
                    if (r.ok && r.dados.status === 'pausado') {
                        window.location.reload();
                        return;
                    }
                    mostrarFeedback('danger', r.dados.motivo || 'Não foi possível pausar o bot.');
                } catch (erro) {
                    mostrarFeedback('danger', 'Erro de comunicação com o servidor. Tente novamente.');
                } finally {
                    definirLoading(false);
                    acaoInput.value = 'preparar';
                }
            };

            if (btnPreparar) btnPreparar.addEventListener('click', prepararPreview);
            if (btnEnviar) btnEnviar.addEventListener('click', aprovarEnviar);
            if (btnPausar) btnPausar.addEventListener('click', pausarBot);
            if (confirmarDestino) {
                // 'change' cobre teclado/acessibilidade; 'click' cobre o clique do mouse.
                confirmarDestino.addEventListener('change', atualizarBotaoEnviar);
                confirmarDestino.addEventListener('click', atualizarBotaoEnviar);
            }

            // Trocar template/mensagem invalida a prévia anterior.
            if (templateSelect) templateSelect.addEventListener('change', function () { limparPreview(); limparFeedback(); });
            if (mensagem) mensagem.addEventListener('input', function () { limparPreview(); limparFeedback(); });

            // A tela usa fetch/JSON: bloqueia qualquer submit nativo (evita renderizar JSON cru).
            form.addEventListener('submit', function (e) { e.preventDefault(); });
        })();
    </script>
@endpush
@endsection
