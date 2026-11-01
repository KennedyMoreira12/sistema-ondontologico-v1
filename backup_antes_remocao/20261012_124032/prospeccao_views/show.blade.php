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

<div class="mb-3>
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

                    <div class="mb-3">
                        <label class="form-label small text-muted">Selecionar template</label>
                        <select name="template_id" class="form-select form-select-sm" id="templateSelect">
                            <option value="">Nenhum (personalizado)</option>
                            @foreach (config('prospeccao.templates') as $key => $t)
                                <option value="{{ $key }}" {{ $key === old('template_id') ? 'selected' : '' }}>
                                    {{ $t['name'] }}
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

                    <button type="submit" class="btn btn-sm btn-outline-primary" id="btnPreparar">
                        <i class="bi bi-eye me-1"></i> Preparar preview
                    </button>
                    <button type="submit" class="btn btn-sm btn-success disabled" id="btnEnviar" disabled>
                        <i class="bi bi-send me-1"></i> Aprovar e enviar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPausarBot">
                        <i class="bi bi-pause-circle me-1"></i> Pausar bot
                    </button>
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

            {{-- Área de preview (será preenchido via AJAX ou enviado) --}}
            @if (isset($status) && $status === 'preparado')
                <h4 class="mt-3">Preview da mensagem</h4>
                <div class="alert alert-light p-3" id="previewArea">
                    <p><strong>Prospect:</strong> {{ $prospect->name }}</p>
                    <p><strong>Telefone:</strong> {{ $prospect->phone_original ?? '—' }}</p>
                    <p><strong>Template:</strong> {{ $template_nome ?? 'Personalizado' }}</p>
                    <p><strong>Conteúdo:</strong></p>
                    <pre id="previewText">{{ $corpo ?? '' }}</pre>
                </div>
                <button type="submit" class="btn btn-sm btn-success mt-2" id="btnConfirmarEnviar">
                    <i class="bi bi-check2 me-1"></i> Confirmar e enviar
                </button>
            @endif
        </div>
    </div>
    {{-- Fim da Área de Mensagem Comercial --}}
@endif

@push('scripts')
    <script>
        // Inicialização: esconder área de preview ao carregar
        document.getElementById('previewArea')?.classList.add('d-none');
        document.getElementById('btnConfirmarEnviar')?.classList.add('d-none');

        // Quando o formulário de comercial for submetido
        const form = document.getElementById('formComercial');
        if (form) {
            document.getElementById('btnPausarBot')?.addEventListener('click', function () {
                form.elements['acao'].value = 'pausar_bot';
                form.submit();
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const btnEnviar = document.getElementById('btnEnviar');
                const btnPreparar = document.getElementById('btnPreparar');
                const btnConfirmar = document.getElementById('btnConfirmarEnviar');
                const previewArea = document.getElementById('previewArea');
                const templateSelect = document.getElementById('templateSelect');
                const msnMsg = document.querySelector('small.text-muted');

                // Resetar estados anteriores
                btnEnviar.disabled = true;
                btnPreparar.disabled = false;
                btnConfirmar.classList.add('d-none');
                previewArea.classList.add('d-none');
                msnMsg?.classList.add('d-none');

                // Identificar a ação
                const acao = form.elements['acao'].value;

                if (acao === 'preparar') {
                    // Habilitar bot de confirmar envio após preview
                    btnPreparar.disabled = true;
                    // O servidor vai retornar JSON com status=preparado e preencher o preview
                    // Via AJAX mais limpo, mas por enquanto submeter o form e deixar o PHP
                    // renderizar a área de preview. O navegador recarregará com os parâmetros.
                    // Para evitar reload, fazemos um fetch em vez de submit tradicional.
                    fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'preparado') {
                            // Preencher preview
                            document.getElementById('previewText').textContent = data.corpo || '';
                            previewArea.classList.remove('d-none');
                            btnConfirmarEnviar.classList.remove('d-none');
                            btnConfirmarEnviar.disabled = false;
                            msnMsg?.classList.remove('d-none');
                            msnMsg.textContent = 'Preview pronto. Clique em "Confirmar e enviar" para prosseguir.';
                        } else {
                            alert('Erro ao preparar: ' + (data.motivo || 'unknown'));
                        }
                    })
                    .catch(err => {
                        console.error('Erro:', err);
                        alert('Erro de comunicação com o servidor.');
                    });
                    return;
                }

                if (acao === 'aprovar_enviar') {
                    // Validar que há preview pronto antes de enviar
                    if (!previewArea.classList.contains('d-none')) {
                        // Obter o corpo do preview
                        const corpo = document.getElementById('previewText').textContent;
                        // Atualizar o campo mensagem hidden com o corpo preview
                        // e submeter novamente com acao=aprovar_enviar
                        const msgInput = form.elements['mensagem'];
                        if (msgInput) {
                            msgInput.value = corpo;
                            // Substituir a ação do formulário
                            form.elements['acao'].value = 'aprovar_enviar';
                            form.submit();
                        }
                    } else {
                        alert('Nenhum preview disponível. Prepare a mensagem primeiro.');
                    }
                    return;
                }

                if (acao === 'pausar_bot') {
                    form.submit();
                    return;
                }

                if (acao === 'retomar_bot') {
                    form.submit();
                    return;
                }
            });

            // Ao mudar o template, atualizar a preview se houver um corpo prévio
            document.getElementById('templateSelect').addEventListener('change', function() {
                const formData = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'preparado') {
                        document.getElementById('previewText').textContent = data.corpo || '';
                        document.getElementById('previewArea').classList.remove('d-none');
                        document.getElementById('btnConfirmarEnviar').classList.remove('d-none');
                        document.getElementById('btnConfirmarEnviar').disabled = false;
                    }
                });
            });
        }
    </script>
@endpush