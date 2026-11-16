@php
    $itens = $itens ?? [];
@endphp

@forelse ($itens as $item)
    @php
        $lida = $item->leituras->where('user_id', auth()->id())->isNotEmpty();
    @endphp
    <a href="{{ $item->url ?? route('notificacoes.index') }}"
        class="list-group-item list-group-item-action p-3
            @if (!$lida) fw-bold bg-light @endif
            {{ $item->prioridade === 'critical' ? 'border-start border-danger' : '' }}
            d-flex justify-content-between align-items-start"
        data-notificacao-id="{{ $item->id }}"
        data-sco-marcar-lida="{{ route('notificacoes.ler', $item->id) }}">
        <div class="d-flex align-items-start gap-2 flex-shrink-0">
            <i class="bi {{ $item->icone() }} {{ $item->prioridade === 'critical' ? 'text-danger' : 'text-muted' }} mt-1"></i>
        </div>
        <div class="flex-grow-1 min-width-0">
            <div class="d-flex justify-content-between align-items-start mb-1 gap-2">
                <span class="{{ $item->prioridade === 'critical' ? 'text-danger fw-bold' : 'text-dark fw-bold' }} small">{{ $item->titulo }}</span>
                <span class="badge bg-{{ $item->prioridade === 'critical' ? 'danger' : ($item->prioridade === 'warning' ? 'warning' : 'primary') }} small">
                    {{ $item->rotuloPrioridade() }}
                </span>
            </div>
            @if ($item->mensagem)
                <div class="small text-body-secondary">{{ $item->mensagem }}</div>
            @endif
            <div class="mt-1">
                <small class="text-muted">{{ $item->created_at?->diffForHumans() }}</small>
            </div>
        </div>
        @if (!$lida)
            <span class="badge bg-light text-primary border rounded-pill py-1 px-2 small">Não lida</span>
        @endif
    </a>
@empty
    <div class="list-group-item text-center text-muted small py-3">
        Sem notificações novas.
    </div>
@endforelse
