@php
    use App\Models\Notification;
    use App\Models\NotificationRead;

    $naoLidas = 0;
    $itens = [];

    if (auth()->check()) {
        $usuario = auth()->user();
        $query = Notification::query()->ativas();

        if (! $usuario->isAdmin()) {
            $permissoes = $usuario->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('name')->all())
                ->unique()
                ->values()
                ->all();

            $query = $query->where(function ($q) use ($permissoes) {
                $q->whereNull('permissao')->orWhereIn('permissao', $permissoes);
            });
        }

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
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-{{ $naoLidas > 0 ? 'danger' : 'light' }} border"
            style="font-size: 0.65rem; {{ $naoLidas > 0 ? '' : 'color: var(--bs-border-color);' }}"
            aria-hidden="true">
            @if ($naoLidas > 0)
                {{ min($naoLidas, 99) }}
            @else
                0
            @endif
        </span>
    </button>

    <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 360px; max-height: 520px;"
        aria-labelledby="scoSino">
        <div class="dropdown-header d-flex justify-content-between align-items-center p-3 border-bottom">
            <span class="small fw-bold text-dark">Notificações</span>
            <a href="{{ route('notificacoes.index') }}" class="small text-decoration-none">Ver tudo</a>
        </div>

        <div class="list-group list-group-flush" id="scoSinoLista">
            @include('notificacoes._item', ['nao_lidas' => $itens, 'preferencias' => session('notificacoes_preferencias', [])])
        </div>

        <div class="dropdown-footer text-center py-2 border-top">
            <a class="dropdown-item text-primary" href="{{ route('notificacoes.index') }}">Histórico de notificações</a>
            <button class="dropdown-item text-primary" data-bs-toggle="modal" data-bs-target="#scoPreferenciasNotificacoes">
                <i class="bi bi-gear me-1"></i>Preferências de som
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Carrega as preferências de som do usuário (sons ligados/desligados, volume, categorias).
    async function scoCarregarPreferenciasSons() {
        try {
            const resposta = await fetch('{{ route('notificacoes.preferencias') }}', {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (resposta.ok) {
                const dados = await resposta.json();
                window.scoPreferenciasSons = dados.preferencias;
            }
        } catch (erro) {
            // Sem rede: fica com os padrões (sons ligados).
            window.scoPreferenciasSons = { sons_ativos: true, volume: 60, categorias: {} };
        }
    }

    // Evita tocar o mesmo som duas vezes: guarda os ids já anunciados.
    window.scoSonsTocados = window.scoSonsTocados || new Set();

    document.addEventListener('alerta:novo', (evento) => {
        const { id, prioridade, categoria, titulo } = evento.detail;

        if (! window.scoPreferenciasSons?.sons_ativos) {
            return;
        }

        const categorias = window.scoPreferenciasSons?.categorias ?? {};
        if (categorias[categoria] === false) {
            return;
        }

        if (window.scoSonsTocados.has(id)) {
            return;
        }

        window.scoSonsTocados.add(id);

        const urgente = prioridade === 'critical';

        const contexto = new (window.AudioContext || window.webkitAudioContext)();
        const agora = contexto.currentTime;
        const oscilador = contexto.createOscillator();
        const ganho = contexto.createGain();

        oscilador.type = urgente ? 'triangle' : 'sine';
        oscilador.frequency.setValueAtTime(urgente ? 660 : 880, agora);
        oscilador.frequency.exponentialRampToValueAtTime(urgente ? 880 : 1320, agora + 0.15);

        ganho.gain.setValueAtTime(0.05 * (window.scoPreferenciasSons?.volume ?? 60) / 100, agora);
        ganho.gain.exponentialRampToValueAtTime(0.001, agora + (urgente ? 0.5 : 0.2));

        oscilador.connect(ganho);
        ganho.connect(contexto.destination);

        oscilador.start(agora);
        oscilador.stop(agora + (urgente ? 0.6 : 0.3));
    });

    // Atualiza o contador e a lista do sino via polling.
    async function scoAtualizarSino() {
        try {
            const resposta = await fetch('{{ route('notificacoes.nao-lidas') }}', {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (! resposta.ok) {
                return;
            }

            const dados = await resposta.json();
            const contador = document.getElementById('scoSinoContador');
            const lista = document.getElementById('scoSinoLista');

            if (contador) {
                contador.textContent = dados.nao_lidas;
                contador.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill border ' +
                    (dados.nao_lidas > 0 ? 'bg-danger' : 'bg-light text-muted');
            }

            // Dispara eventos de som só para itens novos.
            (dados.itens || []).forEach((item) => {
                if (! window.scoSonsTocados.has(item.id)) {
                    window.scoSonsTocados.add(item.id);
                    const evento = new CustomEvent('alerta:novo', {
                        detail: {
                            id: item.id,
                            prioridade: item.prioridade,
                            categoria: item.categoria,
                            titulo: item.titulo,
                        },
                    });
                    document.dispatchEvent(evento);
                }
            });
        } catch (erro) {
            // Falha silenciosa: o próximo ciclo tenta de novo.
        }
    }

    scoCarregarPreferenciasSons();
    scoAtualizarSino();

    // Polling: a cada 30s enquanto a aba está visível, a cada 90s quando oculta.
    let intervalo = 30000;
    const atualizar = () => {
        scoAtualizarSino();
        intervalo = document.hidden ? 90000 : 30000;
        clearTimeout(window.scoSinoTimer);
        window.scoSinoTimer = setTimeout(atualizar, intervalo);
    };

    document.addEventListener('visibilitychange', () => {
        if (! document.hidden) {
            atualizar();
        }
    });

    setTimeout(atualizar, intervalo);
</script>
@endpush
