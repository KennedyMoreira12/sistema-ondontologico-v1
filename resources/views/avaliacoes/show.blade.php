@extends('layouts.app')

@section('title', 'Ficha de avaliação')

@section('page-title', 'Ficha de avaliação')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.avaliacoes.index', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Ficha de avaliação</h1>
                <p class="text-muted small mb-0">
                    {{ $paciente->nome }} — {{ $avaliacao->data->format('d/m/Y') }}
                    @if ($avaliacao->estaFinalizada())
                        <span class="badge text-bg-success ms-2">Finalizada</span>
                    @else
                        <span class="badge text-bg-secondary ms-2">Rascunho</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('avaliacao.update')
                @unless ($avaliacao->estaFinalizada())
                    <a href="{{ route('pacientes.avaliacoes.edit', [$paciente, $avaliacao]) }}" class="btn btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </a>
                    <form method="POST" action="{{ route('pacientes.avaliacoes.finalize', [$paciente, $avaliacao]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-patch-check me-1"></i>Finalizar
                        </button>
                    </form>
                @endunless
            @endcan
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Profissional responsável</dt>
                <dd class="col-sm-9">{{ $avaliacao->profissional?->nome ?? 'Não informado' }}</dd>

                <dt class="col-sm-3">Queixa principal</dt>
                <dd class="col-sm-9">{{ $avaliacao->queixa_principal }}</dd>

                @if ($avaliacao->historia_clinica)
                    <dt class="col-sm-3">História clínica</dt>
                    <dd class="col-sm-9" style="white-space: pre-line;">{{ $avaliacao->historia_clinica }}</dd>
                @endif

                @if ($avaliacao->exame_fisico)
                    <dt class="col-sm-3">Exame físico</dt>
                    <dd class="col-sm-9" style="white-space: pre-line;">{{ $avaliacao->exame_fisico }}</dd>
                @endif

                @if ($avaliacao->hipotese_clinica)
                    <dt class="col-sm-3">Hipótese clínica</dt>
                    <dd class="col-sm-9" style="white-space: pre-line;">{{ $avaliacao->hipotese_clinica }}</dd>
                @endif

                @if ($avaliacao->objetivos)
                    <dt class="col-sm-3">Objetivos</dt>
                    <dd class="col-sm-9" style="white-space: pre-line;">{{ $avaliacao->objetivos }}</dd>
                @endif

                @if ($avaliacao->plano_terapeutico)
                    <dt class="col-sm-3">Plano terapêutico</dt>
                    <dd class="col-sm-9" style="white-space: pre-line;">{{ $avaliacao->plano_terapeutico }}</dd>
                @endif

                @if ($avaliacao->observacoes)
                    <dt class="col-sm-3">Observações</dt>
                    <dd class="col-sm-9" style="white-space: pre-line;">{{ $avaliacao->observacoes }}</dd>
                @endif

                @if ($avaliacao->finalizada_em)
                    <dt class="col-sm-3">Finalizada em</dt>
                    <dd class="col-sm-9">{{ $avaliacao->finalizada_em->format('d/m/Y H:i') }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <p class="text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        Registro preenchido pelo profissional. O sistema não sugere diagnóstico nem altera
        o conteúdo de ficha já finalizada.
    </p>
@endsection
