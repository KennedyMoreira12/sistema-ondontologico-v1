@extends('layouts.app')

@section('title', 'Evoluções por sessão')

@section('page-title', 'Evoluções por sessão')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Evoluções por sessão</h1>
                <p class="text-muted small mb-0">{{ $paciente->nome }}</p>
            </div>
        </div>
        @can('evolucao.create')
            <a href="{{ route('pacientes.evolucoes-sessao.create', $paciente) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nova evolução
            </a>
        @endcan
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Registro da sessão</th>
                        <th>Profissional</th>
                        <th>Dor</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evolucoes as $evolucao)
                        <tr>
                            <td>{{ $evolucao->data->format('d/m/Y') }}</td>
                            <td class="text-truncate" style="max-width: 320px;">{{ $evolucao->conteudo }}</td>
                            <td>{{ $evolucao->profissional?->nome ?? 'Não informado' }}</td>
                            <td>
                                @if ($evolucao->dor_antes !== null || $evolucao->dor_depois !== null)
                                    {{ $evolucao->dor_antes ?? '—' }} &rarr; {{ $evolucao->dor_depois ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($evolucao->estaFinalizada())
                                    <span class="badge text-bg-success">Finalizada</span>
                                @else
                                    <span class="badge text-bg-secondary">Rascunho</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('evolucao.update')
                                    @unless ($evolucao->estaFinalizada())
                                        <a href="{{ route('pacientes.evolucoes-sessao.edit', [$paciente, $evolucao]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('pacientes.evolucoes-sessao.finalize', [$paciente, $evolucao]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Finalizar">
                                                <i class="bi bi-patch-check"></i>
                                            </button>
                                        </form>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Nenhuma evolução registrada para este paciente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $evolucoes->links() }}
        </div>
    </div>
@endsection
