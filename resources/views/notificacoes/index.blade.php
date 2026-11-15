@extends('layouts.app')

@section('title', 'Notificações')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Central de notificações</h1>
        <a href="{{ route('notificacoes.ler-todas') }}"
            class="btn btn-outline-primary btn-sm {{ $naoLidas === 0 ? 'd-none' : '' }}"
            hx-post="{{ route('notificacoes.ler-todas') }}"
            hx-swap="none"
            hx-on::after-request="location.reload()">
            <i class="bi bi-check2-all me-1"></i>Marcar todas como lidas
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('notificacoes.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="categoria" class="form-label small">Categoria</label>
                    <select id="categoria" name="categoria" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria }}" {{ $request->input('categoria') === $categoria ? 'selected' : '' }}>
                                {{ ucfirst($categoria) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="prioridade" class="form-label small">Prioridade</label>
                    <select id="prioridade" name="prioridade" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach ($prioridades as $prioridade)
                            <option value="{{ $prioridade }}" {{ $request->input('prioridade') === $prioridade ? 'selected' : '' }}>
                                {{ ucfirst($prioridade) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small">Status</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        <option value="pendentes" {{ $request->input('status', 'pendentes') === 'pendentes' ? 'selected' : '' }}>Pendentes</option>
                        <option value="resolvidas" {{ $request->input('status') === 'resolvidas' ? 'selected' : '' }}>Resolvidas</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="w-1">Prioridade</th>
                            <th>Título</th>
                            <th class="w-25">Mensagem</th>
                            <th class="w-1">Categoria</th>
                            <th class="w-1">Data</th>
                            <th class="w-1 text-end">Situação</th>
                            <th class="w-1 text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($notificacoes as $notificacao)
                            <tr @class(['table-warning' => $notificacao->prioridade === 'warning', 'table-danger' => $notificacao->prioridade === 'critical'])]
                                <td>
                                    <span class="badge bg-{{ $notificacao->prioridade === 'critical' ? 'danger' : ($notificacao->prioridade === 'warning' ? 'warning' : 'primary') }}">
                                        {{ $notificacao->rotuloPrioridade() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold">{{ $notificacao->titulo }}</div>
                                    @if ($notificacao->url)
                                        <a href="{{ $notificacao->url }}" class="small text-decoration-none">
                                            <i class="bi bi-box-arrow-right me-1"></i>Abrir registro
                                        </a>
                                    @endif
                                </td>
                                <td class="text-body-secondary small">{{ $notificacao->mensagem ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border small">{{ ucfirst($notificacao->categoria) }}</span>
                                </td>
                                <td class="small">{{ $notificacao->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($notificacao->estaResolvida())
                                        <span class="badge bg-success text-white small">Resolvida</span>
                                    @elseif ($notificacao->expirou())
                                        <span class="badge bg-secondary text-white small">Expirada</span>
                                    @else
                                        <span class="badge bg-warning text-dark small">Pendente</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('notificacoes.ler', $notificacao) }}" class="d-inline" style="display:inline">
                                        @csrf
                                        @method('POST')
                                        <button type="submit" class="btn btn-{{ $notificacao->leituras()->where('user_id', auth()->id())->exists() ? 'outline' : 'primary' }}-sm btn-sm"
                                            hx-post="{{ route('notificacoes.ler', $notificacao) }}"
                                            hx-swap="none">
                                            <i class="bi bi-{{ $notificacao->leituras()->where('user_id', auth()->id())->exists() ? 'check2' : 'check' }}"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Sem notificações.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer small text-muted">
            {{ $notificacoes->links() }}
        </div>
    </div>
@endsection
