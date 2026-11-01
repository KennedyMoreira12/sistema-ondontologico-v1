@extends('layouts.app')

@section('title', 'Pacientes')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Pacientes</h1>
            <p class="text-muted small mb-0">{{ $pacientes->total() }} registro(s) encontrado(s)</p>
        </div>
        @can('pacientes.create')
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('pacientes.importar.create') }}" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Importar planilha
                </a>
                <a href="{{ route('pacientes.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Novo paciente
                </a>
            </div>
        @endcan
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('pacientes.index') }}" class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar por nome, CPF, e-mail ou telefone">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Todos os status</option>
                        <option value="ativo" @selected(request('status') === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(request('status') === 'inativo')>Inativo</option>
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
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Telefone</th>
                            <th>Nascimento</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pacientes as $paciente)
                            <tr>
                                <td>{{ $paciente->id }}</td>
                                <td>{{ $paciente->nome }}</td>
                                <td>{{ $paciente->cpf ? \Illuminate\Support\Str::mask($paciente->cpf, '*', 3, 7) : '—' }}</td>
                                <td>{{ $paciente->phone ?? $paciente->celular ?? '—' }}</td>
                                <td>{{ $paciente->nascimento?->translatedFormat('d/m/Y') ?? '—' }}</td>
                                <td>
                                    @if ($paciente->status === 'ativo')
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-sm btn-outline-primary" title="Ficha">
                                        <i class="bi bi-person-lines-fill"></i>
                                    </a>
                                    @can('pacientes.update')
                                        <a href="{{ route('pacientes.edit', $paciente) }}" class="btn btn-sm btn-outline-secondary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nenhum paciente encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $pacientes->links() }}
            </div>
        </div>
    </div>
@endsection