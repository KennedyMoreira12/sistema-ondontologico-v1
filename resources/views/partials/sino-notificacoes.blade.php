@php
    use App\Models\Notification;

    $naoLidas = 0;
    $itens = [];

    if (auth()->check()) {
        $usuario = auth()->user();
        $usuario->loadMissing('roles.permissions');

        $query = Notification::query()->ativas()->visiveisPara($usuario);

        $naoLidas = (clone $query)
            ->whereDoesntHave('leituras', fn ($q) => $q->where('user_id', $usuario->id))
            ->count();

        $itens = (clone $query)
            ->with(['leituras' => fn ($q) => $q->where('user_id', $usuario->id)])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
    }
@endphp

<!-- Sino da central de notificações (PARTE 8). -->
<div class="sco-notificacoes position-relative d-flex align-items-center">
    <button type="button"
        id="scoSino"
        class="btn btn-light position-relative d-flex align-items-center gap-1 p-0"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="Notificações">
        <i class="bi bi-bell fs-5"></i>
        <span id="scoSinoContador"
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill border {{ $naoLidas > 0 ? 'bg-danger' : 'bg-light text-muted' }}"
            style="font-size: 0.65rem;"
            aria-hidden="true">
            {{ $naoLidas > 0 ? min($naoLidas, 99) : 0 }}
        </span>
    </button>

    <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 360px; max-height: 520px;"
        aria-labelledby="scoSino">
        <div class="dropdown-header d-flex justify-content-between align-items-center p-3 border-bottom">
            <span class="small fw-bold text-dark">Notificações</span>
            <a href="{{ route('notificacoes.index') }}" class="small text-decoration-none">Ver tudo</a>
        </div>

        <div class="list-group list-group-flush" id="scoSinoLista">
            @include('notificacoes._item', ['itens' => $itens])
        </div>

        <div class="dropdown-footer text-center py-2 border-top">
            <a class="dropdown-item text-primary" href="{{ route('notificacoes.index') }}">Histórico de notificações</a>
            <button type="button" class="dropdown-item text-primary" data-bs-toggle="modal" data-bs-target="#scoPreferenciasNotificacoes">
                <i class="bi bi-gear me-1"></i>Preferências de som
            </button>
        </div>
    </div>
</div>

{{--
    Alerta central: notificações urgentes aparecem no meio da tela, uma por
    vez, com fila. Fica fora do dropdown de propósito — o sino pode estar
    fechado quando o alerta chega.
--}}
<div id="scoAlertaCentralOverlay"
    class="sco-alerta-central"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="scoAlertaCentralTitulo"
    aria-describedby="scoAlertaCentralMensagem"
    hidden>
    <div class="sco-alerta-central-card" id="scoAlertaCentralCard" tabindex="-1">
        <div class="sco-alerta-central-topo">
            <span class="sco-alerta-central-selo">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                Urgente
            </span>
            <button type="button" class="btn-close" id="scoAlertaCentralFechar" aria-label="Dispensar alerta"></button>
        </div>

        <h2 class="sco-alerta-central-titulo" id="scoAlertaCentralTitulo"></h2>
        <p class="sco-alerta-central-mensagem" id="scoAlertaCentralMensagem"></p>

        <div class="sco-alerta-central-acoes">
            <a href="{{ route('notificacoes.index') }}" class="btn btn-danger btn-sm" id="scoAlertaCentralAbrir">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir registro
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="scoAlertaCentralDispensar">Dispensar</button>
        </div>

        <p class="sco-alerta-central-contagem small text-muted mb-0" id="scoAlertaCentralContagem" hidden></p>
    </div>
</div>

@push('scripts')
<script>
    /*
     * Central de notificações no navegador.
     *
     * O som NÃO substitui a notificação persistida: o alerta já foi gravado
     * pelo backend. Aqui só decidimos se tocamos o aviso e mantemos o contador
     * atualizado enquanto a aba está aberta. Nada é prometido com a aba fechada.
     */
    window.scoNotificacoes = (() => {
        const CHAVE_SONS = 'sco.notificacoes.sons';
        const CHAVE_ALERTAS = 'sco.notificacoes.alertas';
        const PAGINA_CARREGADA_EM = Date.now();
        const LIMITE_HISTORICO = 200;
        const DURACAO_ALERTA = 30000;

        let preferencias = { sons_ativos: true, volume: 60, categorias: {}, alerta_central: true };
        let audioCtx = null;
        let audioLiberado = false;
        let timer = null;

        // Fila do alerta central: um alerta por vez, sem repetir a ocorrência.
        let filaAlertas = [];
        let alertaAtual = null;
        let timerAlerta = null;
        let focoAnterior = null;
        const alertasMostradosNaSessao = new Set();

        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const idsTocados = () => {
            try {
                return new Set(JSON.parse(localStorage.getItem(CHAVE_SONS) || '[]'));
            } catch (erro) {
                return new Set();
            }
        };

        const marcarTocado = (id, prioridade = '') => {
            const ids = idsTocados();
            ids.add(String(id) + ':' + String(prioridade));
            localStorage.setItem(CHAVE_SONS, JSON.stringify([...ids].slice(-LIMITE_HISTORICO)));
        };

        const idsAlerta = () => {
            try {
                return new Set(JSON.parse(localStorage.getItem(CHAVE_ALERTAS) || '[]'));
            } catch (erro) {
                return new Set();
            }
        };

        const alertaJaMostrado = (id, prioridade) => {
            const chave = String(id) + ':' + String(prioridade);

            return alertasMostradosNaSessao.has(chave) || idsAlerta().has(chave);
        };

        const marcarAlertaMostrado = (id, prioridade) => {
            const chave = String(id) + ':' + String(prioridade);
            alertasMostradosNaSessao.add(chave);

            try {
                const ids = idsAlerta();
                ids.add(chave);
                localStorage.setItem(CHAVE_ALERTAS, JSON.stringify([...ids].slice(-LIMITE_HISTORICO)));
            } catch (erro) {
                // Sem localStorage: o Set da sessão já evita repetir.
            }
        };

        const atualizarContagemAlerta = () => {
            const contagem = document.getElementById('scoAlertaCentralContagem');
            if (! contagem) {
                return;
            }

            if (alertaAtual !== null && filaAlertas.length > 0) {
                contagem.hidden = false;
                contagem.textContent = filaAlertas.length === 1
                    ? 'Mais 1 alerta urgente na fila.'
                    : 'Mais ' + filaAlertas.length + ' alertas urgentes na fila.';
            } else {
                contagem.hidden = true;
                contagem.textContent = '';
            }
        };

        const fecharAlerta = () => {
            clearTimeout(timerAlerta);
            timerAlerta = null;

            const overlay = document.getElementById('scoAlertaCentralOverlay');
            if (overlay) {
                overlay.hidden = true;
            }

            document.body.classList.remove('sco-alerta-central-aberto');
            alertaAtual = null;

            if (focoAnterior && typeof focoAnterior.focus === 'function') {
                focoAnterior.focus();
            }
            focoAnterior = null;

            if (filaAlertas.length > 0) {
                setTimeout(mostrarProximoAlerta, 150);
            }
        };

        const mostrarProximoAlerta = () => {
            if (alertaAtual !== null || filaAlertas.length === 0) {
                return;
            }

            const overlay = document.getElementById('scoAlertaCentralOverlay');
            if (! overlay) {
                filaAlertas = [];
                return;
            }

            alertaAtual = filaAlertas.shift();

            const titulo = document.getElementById('scoAlertaCentralTitulo');
            if (titulo) {
                titulo.textContent = alertaAtual.titulo || 'Notificação urgente';
            }

            const mensagem = document.getElementById('scoAlertaCentralMensagem');
            if (mensagem) {
                mensagem.textContent = alertaAtual.mensagem || '';
                mensagem.hidden = ! alertaAtual.mensagem;
            }

            const abrir = document.getElementById('scoAlertaCentralAbrir');
            if (abrir) {
                abrir.href = alertaAtual.url || '{{ route('notificacoes.index') }}';
                abrir.dataset.scoMarcarLida = '{{ url('/notificacoes') }}/' + alertaAtual.id + '/ler';
            }

            atualizarContagemAlerta();

            focoAnterior = document.activeElement;
            overlay.hidden = false;
            document.body.classList.add('sco-alerta-central-aberto');
            document.getElementById('scoAlertaCentralFechar')?.focus();

            clearTimeout(timerAlerta);
            timerAlerta = setTimeout(fecharAlerta, DURACAO_ALERTA);
        };

        const enfileirarAlerta = (item) => {
            // Alerta central é visual: independe do som e das categorias.
            if (! preferencias.alerta_central) {
                return;
            }

            if (item.prioridade !== 'critical' || item.lida) {
                return;
            }

            if (alertaJaMostrado(item.id, item.prioridade)) {
                return;
            }

            marcarAlertaMostrado(item.id, item.prioridade);
            filaAlertas.push(item);
            atualizarContagemAlerta();
            mostrarProximoAlerta();
        };

        // Navegadores só liberam áudio depois de uma interação do usuário.
        const liberarAudio = () => {
            audioLiberado = true;

            if (audioCtx === null) {
                const Contexto = window.AudioContext || window.webkitAudioContext;
                if (Contexto) {
                    audioCtx = new Contexto();
                }
            }

            audioCtx?.resume?.();
        };

        document.addEventListener('pointerdown', liberarAudio, { once: true });
        document.addEventListener('keydown', liberarAudio, { once: true });

        /**
         * Dois tons distintos: aviso comum (curto, agudo) e urgente (mais grave
         * e mais longo). Sem arquivo de áudio: nada para baixar ou cachear.
         */
        const tocar = (urgente = false, volume = null) => {
            if (! audioLiberado) {
                return;
            }

            if (audioCtx === null) {
                const Contexto = window.AudioContext || window.webkitAudioContext;
                if (! Contexto) {
                    return;
                }
                audioCtx = new Contexto();
            }

            const nivel = Math.max(0, Math.min(100, Number(volume ?? preferencias.volume ?? 60))) / 100;
            if (nivel <= 0) {
                return;
            }

            const agora = audioCtx.currentTime;
            const oscilador = audioCtx.createOscillator();
            const ganho = audioCtx.createGain();

            oscilador.type = urgente ? 'triangle' : 'sine';
            oscilador.frequency.setValueAtTime(urgente ? 660 : 880, agora);
            oscilador.frequency.exponentialRampToValueAtTime(urgente ? 990 : 1320, agora + 0.15);

            ganho.gain.setValueAtTime(0.06 * nivel, agora);
            ganho.gain.exponentialRampToValueAtTime(0.0001, agora + (urgente ? 0.5 : 0.22));

            oscilador.connect(ganho);
            ganho.connect(audioCtx.destination);
            oscilador.start(agora);
            oscilador.stop(agora + (urgente ? 0.6 : 0.3));
        };

        const anunciar = (item) => {
            // Já anunciado neste navegador (inclusive antes de recarregar a página).
            // A prioridade entra na chave: um aviso que vira urgente toca de novo.
            const chave = String(item.id) + ':' + String(item.prioridade);

            if (idsTocados().has(chave)) {
                return;
            }

            // Só anuncia o que nasceu depois que a página abriu: os alertas
            // antigos já estão na tela e não devem apitar de novo.
            const criadaEm = Date.parse(item.criada_em ?? '');
            if (Number.isFinite(criadaEm) && criadaEm < PAGINA_CARREGADA_EM) {
                marcarTocado(item.id, item.prioridade);
                return;
            }

            marcarTocado(item.id, item.prioridade);

            // Notificação do navegador: só com a aba aberta (mesmo que oculta).
            // Não é push: com o navegador fechado nada aparece.
            if (preferencias.navegador_ativo
                && document.hidden
                && 'Notification' in window
                && window.Notification.permission === 'granted') {
                try {
                    new window.Notification(item.titulo || 'Notificação', {
                        body: item.mensagem || '',
                        tag: 'sco-notificacao-' + item.id,
                    });
                } catch (erro) {
                    // Alguns navegadores móveis não permitem criar Notification.
                }
            }

            // Som: respeita o interruptor geral e o da categoria.
            if (! preferencias.sons_ativos) {
                return;
            }

            if ((preferencias.categorias ?? {})[item.categoria] === false) {
                return;
            }

            tocar(item.prioridade === 'critical');
        };

        const atualizarContador = (quantidade) => {
            const contador = document.getElementById('scoSinoContador');
            if (! contador) {
                return;
            }

            contador.textContent = quantidade > 99 ? '99+' : String(quantidade);
            contador.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill border ' +
                (quantidade > 0 ? 'bg-danger' : 'bg-light text-muted');
        };

        /**
         * Reenche o dropdown com os itens mais recentes, usando DOM (nunca
         * innerHTML) para não interpretar conteúdo vindo do banco como HTML.
         */
        const atualizarLista = (itens) => {
            const lista = document.getElementById('scoSinoLista');
            if (! lista) {
                return;
            }

            lista.replaceChildren();

            if (! itens || itens.length === 0) {
                const vazio = document.createElement('div');
                vazio.className = 'list-group-item text-center text-muted small py-3';
                vazio.textContent = 'Sem notificações novas.';
                lista.appendChild(vazio);
                return;
            }

            itens.forEach((item) => {
                const link = document.createElement('a');
                link.href = item.url || '{{ route('notificacoes.index') }}';
                link.className = 'list-group-item list-group-item-action p-3 d-flex gap-2 align-items-start' +
                    (item.lida ? '' : ' fw-bold bg-light') +
                    (item.prioridade === 'critical' ? ' border-start border-danger' : '');
                link.dataset.scoMarcarLida = '{{ url('/notificacoes') }}/' + item.id + '/ler';

                const icone = document.createElement('i');
                icone.className = 'bi ' + (item.icone || 'bi-bell') + ' ' +
                    (item.prioridade === 'critical' ? 'text-danger' : 'text-muted') + ' mt-1';
                link.appendChild(icone);

                const corpo = document.createElement('div');
                corpo.className = 'flex-grow-1';

                const titulo = document.createElement('div');
                titulo.className = 'small ' + (item.prioridade === 'critical' ? 'text-danger' : 'text-dark') + ' fw-bold';
                titulo.textContent = item.titulo || '';
                corpo.appendChild(titulo);

                if (item.mensagem) {
                    const mensagem = document.createElement('div');
                    mensagem.className = 'small text-body-secondary';
                    mensagem.textContent = item.mensagem;
                    corpo.appendChild(mensagem);
                }

                const quando = document.createElement('small');
                quando.className = 'text-muted';
                quando.textContent = item.criada_em_texto || '';
                corpo.appendChild(quando);

                link.appendChild(corpo);
                lista.appendChild(link);
            });
        };

        const carregarPreferencias = async () => {
            try {
                const resposta = await fetch('{{ route('notificacoes.preferencias') }}', {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (resposta.ok) {
                    const dados = await resposta.json();
                    preferencias = dados.preferencias ?? preferencias;
                }
            } catch (erro) {
                // Sem rede: mantém os padrões (sons ligados, volume 60).
            }
        };

        const atualizar = async () => {
            try {
                const resposta = await fetch('{{ route('notificacoes.nao-lidas') }}', {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (! resposta.ok) {
                    return;
                }

                const dados = await resposta.json();
                preferencias = dados.preferencias ?? preferencias;

                atualizarContador(dados.nao_lidas ?? 0);
                atualizarLista(dados.itens ?? []);

                (dados.itens ?? []).forEach(anunciar);
                (dados.itens ?? []).forEach(enfileirarAlerta);
            } catch (erro) {
                // Falha silenciosa: o próximo ciclo tenta de novo.
            }
        };

        const agendar = () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                await atualizar();
                agendar();
            }, document.hidden ? 90000 : 30000);
        };

        document.addEventListener('visibilitychange', () => {
            if (! document.hidden) {
                atualizar();
            }
            agendar();
        });

        // Marca como lida antes de navegar; `keepalive` sobrevive à troca de página.
        document.addEventListener('click', (evento) => {
            const alvo = evento.target.closest('[data-sco-marcar-lida]');
            if (! alvo) {
                return;
            }

            const url = alvo.dataset.scoMarcarLida;
            if (! url) {
                return;
            }

            try {
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: {
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            } catch (erro) {
                // A navegação para o registro acontece de qualquer forma.
            }
        });

        document.getElementById('scoAlertaCentralFechar')?.addEventListener('click', fecharAlerta);
        document.getElementById('scoAlertaCentralDispensar')?.addEventListener('click', fecharAlerta);

        // O fundo fecha; clique dentro do cartão não.
        document.getElementById('scoAlertaCentralOverlay')?.addEventListener('click', (evento) => {
            if (evento.target.id === 'scoAlertaCentralOverlay') {
                fecharAlerta();
            }
        });

        document.addEventListener('keydown', (evento) => {
            if (evento.key !== 'Escape') {
                return;
            }

            const overlay = document.getElementById('scoAlertaCentralOverlay');
            if (overlay && ! overlay.hidden) {
                evento.preventDefault();
                fecharAlerta();
            }
        });

        // A navegação para o registro continua; o overlay só sai da frente.
        document.getElementById('scoAlertaCentralAbrir')?.addEventListener('click', fecharAlerta);

        carregarPreferencias().then(atualizar).then(agendar);

        return {
            tocar,
            atualizar,
            definirPreferencias: (novas) => {
                preferencias = { ...preferencias, ...novas };

                if (! preferencias.alerta_central) {
                    filaAlertas = [];

                    if (alertaAtual !== null) {
                        fecharAlerta();
                    }
                }
            },
            preferencias: () => preferencias,
        };
    })();
</script>
@endpush
