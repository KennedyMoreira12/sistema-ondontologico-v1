@extends('layouts.app')

@section('title', 'Pacotes de sessões')

@section('page-title', 'Pacotes de sessões')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Pacotes de sessões</h1>
                <p class="text-muted small mb-0">{{ $paciente->nome }}</p>
            </div>
        </div>
        @can('pacotes.create')
            <a href="{{ route('pacientes.pacotes.create', $paciente) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Novo pacote
            </a>
        @endcan
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Pacote</th>
                        <th>Sessões</th>
                        <th>Valor</th>
                        <th>Validade</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pacotes as $pacote)
                        <tr>
                            <td>{{ $pacote->nome }}</td>
                            <td>
                                {{ $pacote->sessoes_usadas }} / {{ $pacote->total_sessoes }}
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $pacote->total_sessoes > 0 ? round($pacote->sessoes_usadas * 100 / $pacote->total_sessoes) : 0 }}%"></div>
                                </div>
                            </td>
                            <td>{{ number_format((float) $pacote->valor_total, 2, ',', '.') }}</td>
                            <td>{{ $pacote->validade_em?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                @if ($pacote->status === 'ativo')
                                    <span class="badge text-bg-success">Ativo</span>
                                @elseif ($pacote->status === 'concluido')
                                    <span class="badge text-bg-secondary">Concluído</span>
                                @else
                                    <span class="badge text-bg-danger">Cancelado</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('pacientes.pacotes.show', [$paciente, $pacote]) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Nenhum pacote de sessões para este paciente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $pacotes->links() }}
        </div>
    </div>
@endsection
