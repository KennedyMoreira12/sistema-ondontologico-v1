@extends('layouts.app')

@section('title', 'Turmas de Pilates')

@section('page-title', 'Turmas de Pilates')

@section('content')
    @php
        $mesReferencia = $mes->copy();
        $mesAnterior = $mesReferencia->copy()->subMonth()->format('Y-m');
        $mesSeguinte = $mesReferencia->copy()->addMonth()->format('Y-m');
    @endphp

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">Turmas de Pilates</h1>
            <p class="text-muted small mb-0">Grade semanal, calendário do mês e lista de presença por turma.</p>
        </div>
        @can('pacotes.create')
            <a href="{{ route('turmas.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nova turma
            </a>
        @endcan
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold"><i class="bi bi-grid-3x3-gap me-2"></i>Grade semanal</span>
            <span class="text-muted small">{{ $turmas->count() }} turma(s) ativa(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        @foreach (\App\Models\Turma::DIAS as $dia)
                            <th class="text-center">{{ $dia }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach (\App\Models\Turma::DIAS as $numero => $dia)
                            <td class="align-top" style="min-width: 150px;">
                                @forelse ($grade->get($numero, collect()) as $turma)
                                    <a href="{{ route('turmas.show', $turma) }}" class="d-block text-decoration-none mb-2 p-2 rounded border">
                                        <div class="fw-semibold text-body">{{ $turma->nome }}</div>
                                        <div class="small text-muted">
                                            <i class="bi bi-clock me-1"></i>{{ $turma->horaInicioFormatada() }}
                                            @if ($turma->horaFimFormatada())
                                                – {{ $turma->horaFimFormatada() }}
                                            @endif
                                        </div>
                                        <div class="small text-muted">
                                            <i class="bi bi-person-badge me-1"></i>{{ $turma->profissional?->nome ?? 'Sem profissional' }}
                                        </div>
                                        <div class="small text-muted">
                                            <i class="bi bi-people me-1"></i>{{ $turma->pacientes_count }} aluno(s)
                                        </div>
                                    </a>
                                @empty
                                    <span class="text-muted small">—</span>
                                @endforelse
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold"><i class="bi bi-calendar3 me-2"></i>Calendário de {{ $mesReferencia->translatedFormat('F \d\e Y') }}</span>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('turmas.index', ['mes' => $mesAnterior]) }}" class="btn btn-outline-secondary btn-sm" title="Mês anterior"><i class="bi bi-chevron-left"></i></a>
                <form method="GET" action="{{ route('turmas.index') }}" class="d-flex gap-2">
                    <input type="month" name="mes" value="{{ $mesReferencia->format('Y-m') }}" class="form-control form-control-sm">
                    <button class="btn btn-outline-primary btn-sm">Ir</button>
                </form>
                <a href="{{ route('turmas.index', ['mes' => $mesSeguinte]) }}" class="btn btn-outline-secondary btn-sm" title="Próximo mês"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 110px;">Data</th>
                        <th>Turmas do dia</th>
                    </tr>
                </thead>
                <tbody>
                    @php($diasComTurma = collect($calendario)->filter(fn ($dia) => $dia['turmas']->isNotEmpty()))
                    @forelse ($diasComTurma as $dia)
                        <tr>
                            <td class="fw-semibold">{{ $dia['data']->format('d/m') }} <span class="text-muted small">{{ $dia['data']->translatedFormat('D') }}</span></td>
                            <td>
                                @foreach ($dia['turmas'] as $turma)
                                    <a href="{{ route('turmas.show', ['turma' => $turma, 'data' => $dia['data']->toDateString()]) }}" class="badge text-bg-light border text-decoration-none me-1 mb-1">
                                        {{ $turma->horaInicioFormatada() }} · {{ $turma->nome }}
                                    </a>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Nenhuma turma cadastrada para este mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
