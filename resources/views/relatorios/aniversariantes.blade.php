@extends('layouts.app')

@section('title', 'Relatórios — Aniversariantes')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Aniversariantes</h1>
            <p class="text-muted small mb-0">Pacientes que fazem aniversário no mês selecionado. Equivalente às fórmulas "TÁ CHEGANDO!" da planilha ANIVERSARIANTES.xlsx.</p>
        </div>
    </div>

    @include('relatorios.partials.nav')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('relatorios.aniversariantes') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="mes" class="form-label">Mês</label>
                    <select id="mes" name="mes" class="form-select">
                        @php
                            $meses = [
                                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                            ];
                        @endphp
                        @foreach ($meses as $numero => $nome)
                            <option value="{{ $numero }}" {{ $mes == $numero ? 'selected' : '' }}>{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Aniversariantes no mês</div>
                <div class="fs-4 fw-bold">{{ $total }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-info"><div class="card-body">
                <div class="text-muted small">Aniversariando agora (até 2 dias)</div>
                <div class="fs-4 fw-bold text-info">{{ $chegando }}</div>
                <div class="text-muted small">Sinalizador "TÁ CHEGANDO!"</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Lista de aniversariantes</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Paciente</th>
                        <th>Nascimento</th>
                        <th class="text-end">Idade</th>
                        <th class="text-end">Dias até o aniversário</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pacientes as $item)
                        <tr>
                            <td>{{ $item['paciente']->nome }}</td>
                            <td>{{ $item['nascimento']->format('d/m') }}</td>
                            <td class="text-end">{{ $item['idade'] }}</td>
                            <td class="text-end">{{ $item['dias_ate_aniversario'] }}</td>
                            <td class="text-center">
                                @if ($item['chegando'])
                                    <span class="badge text-bg-warning text-dark">
                                        <i class="bi bi-cake me-1"></i>TÁ CHEGANDO!
                                    </span>
                                @else
                                    <span class="badge text-bg-secondary">
                                        <i class="bi bi-calendar me-1"></i>Este mês
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum aniversariante encontrado neste mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
