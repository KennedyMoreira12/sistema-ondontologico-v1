@extends('layouts.app')

@section('title', 'Usuários')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Usuários</h1>
            <p class="text-muted small mb-0">Gestão de acessos ao sistema.</p>
        </div>
        @can('admin.create')
            <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Novo usuário
            </a>
        @endcan
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Clínica</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td><span class="badge bg-primary">{{ $user->role?->label ?? $user->role?->name ?? '—' }}</span></td>
                                <td>{{ $user->clinica?->nome ?? '—' }}</td>
                                <td>
                                    @if ($user->is_active)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('admin.update')
                                        <a href="{{ route('admin.usuarios.edit', $user) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    @endcan
                                    @can('admin.delete')
                                        @if ($user->id !== auth()->id())
                                            <form method="POST" action="{{ route('admin.usuarios.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Excluir o usuário {{ $user->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection