/**
 * Odontograma anatômico do LJ SmartClinic.
 *
 * O payload (window.odontograma) é servido pelo backend com as arcadas, o
 * catálogo de condições, as camadas e o estado atual dos dentes. Este módulo
 * renderiza as duas arcadas em SVG, deixando cada superfície clicável, e
 * persiste cada alteração pelos endpoints do OdontogramaController.
 */
(function () {
    const config = window.odontogramaConfig;
    if (!config) return;

    let payload = JSON.parse(JSON.stringify(window.odontograma));
    let interacoesVinculadas = false;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    const modal = $('#' + config.modalId);

    function abrirPainelModal() {
        if (!modal || typeof bootstrap === 'undefined') return;
        const instancia = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
        instancia.show();
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    function dentesPorNumero() {
        const mapa = {};
        for (const dente of payload.dentes) mapa[dente.numero] = dente;
        return mapa;
    }

    function api(url, options = {}) {
        return fetch(url, {
            method: options.method || 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: options.body ? JSON.stringify(options.body) : undefined,
            credentials: 'same-origin',
        }).then(async (res) => {
            if (res.status === 422) {
                const data = await res.json();
                throw new Error(Object.values(data.errors || {}).flat().join(' '));
            }
            if (!res.ok) {
                throw new Error(res.status === 403 ? 'Sem permissão para esta ação.' : 'Falha na requisição.');
            }
            return res.json();
        });
    }

    function mensagem(texto, tipo = 'success') {
        const area = $('#odontograma-alert');
        if (!area) return;
        area.className = `alert alert-${tipo} alert-dismissible fade show`;
        area.innerHTML = `<span>${esc(texto)}</span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>`;
        window.clearTimeout(area._timeout);
        area._timeout = window.setTimeout(() => area.classList.add('d-none'), 4000);
    }

    function esc(texto) {
        const el = document.createElement('div');
        el.textContent = texto ?? '';
        return el.innerHTML;
    }

    function quadrante(numero) {
        return numero >= 10 ? Math.trunc(numero / 10) : 0;
    }

    function arcadaDoDente(numero) {
        return [1, 2, 5, 6].includes(quadrante(numero)) ? 'superior' : 'inferior';
    }

    function superficieDaRegiao(regiao, numero) {
        const posicao = numero % 10;
        const arcada = arcadaDoDente(numero);
        const mesialDireita = [1, 4, 5, 8].includes(quadrante(numero));

        switch (regiao) {
            case 'topo': return 'vestibular';
            case 'base': return arcada === 'superior' ? 'palatina' : 'lingual';
            case 'centro': return posicao <= 3 ? 'incisal' : 'oclusal';
            case 'esquerda': return mesialDireita ? 'distal' : 'mesial';
            case 'direita': return mesialDireita ? 'mesial' : 'distal';
            default: return null;
        }
    }

    function corDaCamada(camada) {
        return payload.camadas[camada]?.cor || '#6c757d';
    }

    function labelDoDente(dente, notacao) {
        switch (notacao) {
            case 'Universal': return dente.universal;
            case 'Palmer': return dente.palmer;
            default: return String(dente.numero);
        }
    }

    function morfologia(numero) {
        const posicao = numero % 10;
        if (posicao >= 6) return 'molar';
        if (posicao >= 4) return 'premolar';
        return 'anterior';
    }

    /* ------------------------------------------------------------------ */
    /* Renderização SVG                                                    */
    /* ------------------------------------------------------------------ */

    const REGIOES = [
        { chave: 'topo', d: 'M24 34 L40 34 C45 34 48 37 49 42 L15 42 C16 37 19 34 24 34 Z' },
        { chave: 'base', d: 'M15 58 L49 58 C48 63 45 66 40 66 L24 66 C19 66 16 63 15 58 Z' },
        { chave: 'esquerda', d: 'M12 42 L22 40 L22 60 L12 58 C11 52 11 46 12 42 Z' },
        { chave: 'direita', d: 'M52 42 L42 40 L42 60 L52 58 C53 52 53 46 52 42 Z' },
        { chave: 'centro', d: 'M22 40 L42 40 L42 60 L22 60 Z' },
    ];

    const RAIOZES = {
        anterior: 'M26 40 C26 22 24 14 22 6 L34 6 C32 14 30 24 30 40 Z',
        premolar: 'M18 40 L14 4 L24 6 L28 40 Z M46 40 L40 6 L50 4 L46 40 Z',
        molar: 'M14 40 L8 6 L20 4 L26 42 Z M38 42 L44 4 L56 6 L50 40 Z',
    };

    function svgDoDente(dente) {
        const superior = arcadaDoDente(dente.numero) === 'superior';
        const notacao = payload.odontogram.notacao;
        const label = labelDoDente(dente, notacao);
        const sub = notacao === 'FDI' ? '' : String(dente.numero);
        const flip = `translate(0 ${superior ? 0 : 84}) scale(1 ${superior ? 1 : -1})`;

        const arte = [];

        arte.push(`<g class="odontograma-raizes" transform="${flip}"><path d="${RAIOZES[morfologia(dente.numero)]}" /></g>`);
        arte.push(`<g class="odontograma-coroa" transform="${flip}">`);
        arte.push('<path class="odontograma-contorno" d="M16 42 L15 58 Q15 66 24 66 L40 66 Q49 66 49 58 L48 42 Q48 34 40 34 L24 34 Q16 34 16 42 Z" />');

        for (const regiao of REGIOES) {
            const superficie = superficieDaRegiao(regiao.chave, dente.numero);
            const condicoes = dente.condicoes.filter((cnd) => cnd.superficie === superficie);
            const estilo = condicoes.length
                ? { cls: `camada-${condicoes[0].camada}`, cor: corDaCamada(condicoes[0].camada) }
                : { cls: 'livre', cor: '' };

            arte.push(`<path class="odontograma-regiao ${estilo.cls}" d="${regiao.d}" data-surface="${superficie}"
                style="${estilo.cor ? `fill:${estilo.cor};` : ''}" />`);
        }

        const inteiras = dente.condicoes.filter((cnd) => !cnd.superficie);
        if (inteiras.length > 0) {
            arte.push(`<circle class="odontograma-inteiro" cx="52" cy="38" r="4" fill="${corDaCamada(inteiras[0].camada)}" />`);
        }

        if (dente.status === 'ausente') {
            arte.push(`<g class="odontograma-x" transform="${flip}">
                <line x1="16" y1="36" x2="48" y2="64" stroke-width="5" />
                <line x1="48" y1="36" x2="16" y2="64" stroke-width="5" />
            </g>`);
        }

        if (dente.status === 'erupcionado') {
            arte.push(`<path class="odontograma-erupcionado" d="M16 42 L15 58 Q15 66 24 66 L40 66 Q49 66 49 58 L48 42 Q48 34 40 34 L24 34 Q16 34 16 42 Z" />`);
        }

        arte.push('</g>');

        arte.push(`<text x="32" y="${superior ? 79 : 10}" class="odontograma-label" text-anchor="middle">${esc(label)}</text>`);
        if (sub) {
            arte.push(`<text x="32" y="${superior ? 10 : 79}" class="odontograma-sub" text-anchor="middle">${esc(sub)}</text>`);
        }

        return `<svg viewBox="0 0 64 84" class="odontograma-tooth" role="button" tabindex="0"
            aria-label="Dente ${dente.numero}" data-numero="${dente.numero}">${arte.join('')}</svg>`;
    }

    function renderizar() {
        const alvo = $('#odontograma-arcadas');
        if (!alvo) return;

        const mapa = dentesPorNumero();
        let html = '';

        for (const arcada of ['superior', 'inferior']) {
            html += `<div class="odontograma-arcada ${arcada}">`;
            html += `<div class="odontograma-arcada-titulo">${arcada === 'superior' ? 'Arcada superior' : 'Arcada inferior'}</div>`;

            for (const linha of payload.arcadas[arcada]) {
                if (payload.arcadas[arcada].length > 1) {
                    html += `<div class="odontograma-linha-label">${esc(linha.label)}</div>`;
                }
                html += '<div class="odontograma-linha">';
                for (const numero of linha.dentes) {
                    const dente = mapa[numero] || {
                        numero, status: 'presente', condicoes: [],
                        nome: '', universal: numero, palmer: numero,
                    };
                    html += svgDoDente(dente);
                }
                html += '</div>';
            }
            html += '</div>';
        }

        alvo.innerHTML = html;
        vincularInteracoes(alvo);

        const atualizado = $('#odontograma-atualizado');
        if (atualizado) atualizado.textContent = payload.odontogram.atualizado_em || '—';
    }

    /* ------------------------------------------------------------------ */
    /* Interação                                                           */
    /* ------------------------------------------------------------------ */

    function vincularInteracoes(container) {
        if (!config.canEdit || interacoesVinculadas) return;
        interacoesVinculadas = true;

        container.addEventListener('click', (e) => {
            const svg = e.target.closest('.odontograma-tooth');
            if (!svg) return;
            const superficie = e.target.closest('[data-surface]')?.dataset.surface || null;
            abrirPainel(Number(svg.dataset.numero), superficie);
        });

        container.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const svg = e.target.closest('.odontograma-tooth');
            if (!svg) return;
            e.preventDefault();
            const superficie = e.target.closest('[data-surface]')?.dataset.surface || null;
            abrirPainel(Number(svg.dataset.numero), superficie);
        });
    }

    function abrirPainel(numero, superficieSugerida) {
        const dente = dentesPorNumero()[numero] || {
            numero, status: 'presente', condicoes: [],
            nome: '', universal: numero, palmer: numero, observacao: null,
        };

        $('#toothNumero').textContent = numero;
        $('#toothNumeroInput').value = numero;
        $('#toothNome').textContent = dente.nome || '';
        $('#toothStatus').value = dente.status || 'presente';

        $('#toothNotacoes').innerHTML = ['FDI', 'Universal', 'Palmer'].map((n) =>
            `<span class="badge text-bg-light border me-1">${n}: <strong>${n === 'FDI' ? numero : (n === 'Universal' ? dente.universal : dente.palmer)}</strong></span>`
        ).join('');

        renderSuperficies(dente, superficieSugerida);
        renderCondicoesDoPainel(dente);
        $('#toothObservacao').value = dente.observacao || '';

        abrirPainelModal();
    }

    function renderSuperficies(dente, superficieSugerida) {
        const select = $('#toothSuperficie');
        if (!select) return;

        select.innerHTML = [
            '<option value="">Dente inteiro (mapeia todas as faces)</option>',
            ...Object.keys(payload.superficies).map((chave) =>
                `<option value="${chave}">${esc(payload.superficies[chave])}</option>`
            ),
        ].join('');

        select.value = superficieSugerida || '';

        const dica = $('#toothSuperficieDica');
        if (dica) {
            const arcada = arcadaDoDente(dente.numero);
            const posicao = dente.numero % 10;
            const eixo = arcada === 'superior' ? 'palatina' : 'lingual';
            const face = posicao <= 3 ? 'incisal' : 'oclusal';
            dica.textContent = `Arcada ${arcada} — face ${eixo}; dente ${posicao <= 3 ? 'anterior' : 'posterior'} usa ${face}.`;
        }
    }

    function renderCondicoesDoPainel(dente) {
        const lista = $('#toothCondicoesLista');
        if (!lista) return;

        if (dente.condicoes.length === 0) {
            lista.innerHTML = '<li class="list-group-item text-muted small">Nenhuma condição. Dente hígido.</li>';
            return;
        }

        lista.innerHTML = dente.condicoes.map((cnd) => {
            const superficiesLabel = cnd.superficie ? payload.superficies[cnd.superficie] || cnd.superficie : 'Dente inteiro';
            const cor = corDaCamada(cnd.camada);
            return `<li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                <div>
                    <span class="d-inline-flex align-items-center gap-1 small">
                        <span style="width:10px;height:10px;border-radius:50%;background:${cor};display:inline-block"></span>
                        <strong>${esc(cnd.nome || 'Condição')}</strong>
                        <span class="badge" style="background:${cor}">${esc(payload.camadas[cnd.camada]?.label || cnd.camada)}</span>
                        <span class="text-muted">· ${esc(superficiesLabel)}</span>
                    </span>
                    ${cnd.descricao ? `<div class="text-muted small mt-1">${esc(cnd.descricao)}</div>` : ''}
                    <div class="text-muted fst-italic" style="font-size:.72rem">${esc(cnd.criado_em || '')}</div>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm" data-remover-condicao="${cnd.id}" title="Remover condição">
                    <i class="bi bi-x-lg"></i>
                </button>
            </li>`;
        }).join('');
    }

    /* ------------------------------------------------------------------ */
    /* Persistência                                                        */
    /* ------------------------------------------------------------------ */

    function atualizarDenteLocal(denteAtualizado) {
        const mapa = dentesPorNumero();
        mapa[denteAtualizado.numero] = denteAtualizado;
        payload.dentes = Object.values(mapa);
        renderizar();
        abrirPainel(denteAtualizado.numero, null);
    }

    function numeroDoPainel() {
        return Number($('#toothNumeroInput').value);
    }

    async function adicionarCondicao() {
        const numero = numeroDoPainel();
        try {
            const dente = await api(config.urls.conditionStore.replace('DENTE_ID', numero), {
                method: 'POST',
                body: {
                    tooth_condition_id: Number($('#toothCondicao').value),
                    superficie: $('#toothSuperficie').value || null,
                    camada: $('#toothCamada').value,
                    descricao: $('#toothDescricao').value.trim() || null,
                },
            });
            atualizarDenteLocal(dente);
            mensagem(`Condição registrada no dente ${numero}.`);
        } catch (erro) {
            mensagem(erro.message, 'danger');
        }
    }

    async function removerCondicao(id) {
        const numero = numeroDoPainel();
        try {
            const dente = await api(config.urls.conditionDestroy
                .replace('DENTE_ID', numero)
                .replace('CONDICAO_ID', id), { method: 'DELETE' });
            atualizarDenteLocal(dente);
            mensagem('Condição removida.');
        } catch (erro) {
            mensagem(erro.message, 'danger');
        }
    }

    async function salvarDente() {
        const numero = numeroDoPainel();
        try {
            const dente = await api(config.urls.toothUpdate.replace('DENTE_ID', numero), {
                method: 'PUT',
                body: {
                    status: $('#toothStatus').value,
                    observacao: $('#toothObservacao').value.trim() || null,
                },
            });
            atualizarDenteLocal(dente);
            mensagem(`Dente ${numero} atualizado.`);
        } catch (erro) {
            mensagem(erro.message, 'danger');
        }
    }

    async function limparDente() {
        const numero = numeroDoPainel();
        if (!window.confirm(`Limpar o dente ${numero}? Condições e observação serão apagadas.`)) return;
        try {
            const dente = await api(config.urls.toothDestroy.replace('DENTE_ID', numero), { method: 'DELETE' });
            atualizarDenteLocal(dente);
            mensagem(`Dente ${numero} considerado hígido.`);
        } catch (erro) {
            mensagem(erro.message, 'danger');
        }
    }

    async function atualizarSettings(campo, valor) {
        try {
            const resultado = await api(config.urls.settings, { method: 'PUT', body: { [campo]: valor } });
            payload = JSON.parse(JSON.stringify(resultado));
            renderizar();
            mensagem('Odontograma atualizado.');
        } catch (erro) {
            mensagem(erro.message, 'danger');
            renderizar();
        }
    }

    async function carregarHistorico() {
        const corpo = $('#odontograma-historico');
        const spinner = $('#odontograma-historico-spinner');
        if (!corpo || !spinner) return;
        corpo.innerHTML = '';
        spinner.classList.remove('d-none');

        try {
            const dados = await api(config.urls.historico);
            spinner.classList.add('d-none');

            if (!dados.historico.length) {
                corpo.innerHTML = '<div class="text-muted small text-center py-3">Nenhuma alteração registrada ainda.</div>';
                return;
            }

            corpo.innerHTML = dados.historico.map((reg) => {
                const antes = resumo(reg.antes);
                const depois = resumo(reg.depois);
                return `<div class="list-group-item">
                    <div class="d-flex justify-content-between small">
                        <strong>${esc(rotuloAcao(reg.acao))}</strong>
                        <span class="text-muted">${esc(reg.data_hora || '')}</span>
                    </div>
                    <div class="text-muted small">${esc(reg.profissional || 'Profissional não informado')}</div>
                    ${antes ? `<div class="small text-danger" style="word-break:break-word">Antes: <span class="font-monospace">${esc(antes)}</span></div>` : ''}
                    ${depois ? `<div class="small text-success" style="word-break:break-word">Depois: <span class="font-monospace">${esc(depois)}</span></div>` : ''}
                </div>`;
            }).join('');
        } catch (erro) {
            spinner.classList.add('d-none');
            corpo.innerHTML = `<div class="text-danger small text-center py-3">${esc(erro.message)}</div>`;
        }
    }

    function resumo(dados) {
        if (!dados) return '';
        return Object.entries(dados)
            .map(([chave, valor]) => `${chave}=${valor === null || valor === '' ? 'vazio' : valor}`)
            .join(', ');
    }

    function rotuloAcao(acao) {
        const rotulos = {
            'adicionar_condicao_dente': 'Condição adicionada',
            'remover_condicao_dente': 'Condição removida',
            'atualizar_dente': 'Dente atualizado',
            'limpar_dente': 'Dente limpo',
            'atualizar_odontograma': 'Odontograma atualizado',
            'marcar_dente': 'Dente marcado',
        };
        return rotulos[acao] || acao;
    }

    /* ------------------------------------------------------------------ */
    /* Eventos                                                             */
    /* ------------------------------------------------------------------ */

    function vincularEventos() {
        const selDenticao = $('#odontograma-denticao');
        if (selDenticao) selDenticao.value = payload.odontogram.denticao;
        if (selDenticao) selDenticao.addEventListener('change', (e) => atualizarSettings('denticao', e.target.value));

        const selNotacao = $('#odontograma-notacao');
        if (selNotacao) selNotacao.value = payload.odontogram.notacao;
        if (selNotacao) selNotacao.addEventListener('change', (e) => atualizarSettings('notacao', e.target.value));

        const btnNotas = $('#odontograma-notas-salvar');
        if (btnNotas) btnNotas.addEventListener('click', () => atualizarSettings('notas', $('#odontograma-notas').value));

        const btnHistorico = $('#odontograma-historico-btn');
        if (btnHistorico) btnHistorico.addEventListener('click', carregarHistorico);

        if (!config.canEdit) return;

        $('#toothCondicaoAdd')?.addEventListener('click', adicionarCondicao);
        $('#toothSalvar')?.addEventListener('click', salvarDente);
        $('#toothLimpar')?.addEventListener('click', limparDente);

        $('#toothCondicoesLista')?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remover-condicao]');
            if (btn) removerCondicao(btn.dataset.removerCondicao);
        });

        $$$('input[name="toothCamada"]').forEach((radio) => {
            radio.checked = radio.value === 'existente';
        });
    }

    renderizar();
    vincularEventos();
})();