@extends('layouts.app')

@section('title', 'Social')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Social</h1>
            <p class="text-muted small mb-0">{{ $posts->total() }} publicação(ões) encontrada(s)</p>
        </div>
        @can('social.manage')
            <a href="{{ route('social.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Novo Post
            </a>
        @endcan
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('social.index') }}" class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar por título ou tema">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Todos os status</option>
                        <option value="pendente" @selected(request('status') === 'pendente')>Pendente</option>
                        <option value="aprovado" @selected(request('status') === 'aprovado')>Aprovado</option>
                        <option value="rejeitado" @selected(request('status') === 'rejeitado')>Rejeitado</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <th>Tema</th>
                            <th>Plataformas</th>
                            <th>Status</th>
                            <th>Agendado</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($posts as $post)
                            <tr>
                                <td>{{ $post->id }}</td>
                                <td>{{ $post->titulo ?? '—' }}</td>
                                <td>{{ $post->tema ?? '—' }}</td>
                                <td>
                                    @foreach (($post->platforms ?? []) as $plataforma)
                                        <span class="badge bg-secondary text-uppercase">{{ $plataforma }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if ($post->status === 'aprovado')
                                        <span class="badge bg-success">Aprovado</span>
                                    @elseif ($post->status === 'rejeitado')
                                        <span class="badge bg-danger">Rejeitado</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pendente</span>
                                    @endif
                                </td>
                                <td>{{ $post->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('social.show', $post) }}" class="btn btn-sm btn-outline-primary" title="Ver conteúdo">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nenhuma publicação encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $posts->links() }}
            </div>
        </div>
    </div>
@endsection
