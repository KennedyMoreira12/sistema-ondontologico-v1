@extends('layouts.app')

@section('title', 'Avaliações')

@section('page-title', 'Avaliações')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Fichas de avaliação</h1>
                <p class="text-muted small mb-0">{{ $paciente->nome }}</p>
            </div>
        </div>
        @can('avaliacao.create')
            <a href="{{ route('pacientes.avaliacoes.create', $paciente) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nova ficha de avaliação
            </a>
        @endcan
    </div>

    <div class="alert alert-light border d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle mt-1"></i>
        <div class="small">
            A ficha é preenchida pelo profissional responsável. O sistema não sugere diagnóstico,
            não calcula escores e não indica conduta: o que está escrito aqui foi digitado por alguém.
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Queixa principal</th>
                        <th>Profissional</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($avaliacoes as $avaliacao)
                        <tr>
                            <td>{{ $avaliacao->data->format('d/m/Y') }}</td>
                            <td class="text-truncate" style="max-width: 320px;">{{ $avaliacao->queixa_principal }}</td>
                            <td>{{ $avaliacao->profissional?->nome ?? 'Não informado' }}</td>
                            <td>
                                @if ($avaliacao->estaFinalizada())
                                    <span class="badge text-bg-success">Finalizada</span>
                                @else
                                    <span class="badge text-bg-secondary">Rascunho</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('pacientes.avaliacoes.show', [$paciente, $avaliacao]) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Nenhuma ficha de avaliação registrada para este paciente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $avaliacoes->links() }}
        </div>
    </div>
@endsection
