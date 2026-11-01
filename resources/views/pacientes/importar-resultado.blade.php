@extends('layouts.app')

@section('title', 'Resultado da importação')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Resultado da importação</h1>
            <p class="text-muted small mb-0">
                {{ $resultado['arquivo'] }} — {{ number_format($resultado['total_linhas'], 0, ',', '.') }} linha(s) de dados analisada(s).
            </p>
        </div>
    </div>

    @if ($resultado['truncada'])
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            A planilha tinha mais de 3.000 linhas; as excedentes não foram importadas.
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-success-subtle">
                <div class="card-body">
                    <div class="text-muted small">Pacientes criados</div>
                    <div class="display-6 fw-semibold text-success">{{ number_format($resultado['criados'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-warning-subtle">
                <div class="card-body">
                    <div class="text-muted small">Ignorados (já cadastrados)</div>
                    <div class="display-6 fw-semibold text-warning-emphasis">{{ number_format($resultado['ignorados_total'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-danger-subtle">
                <div class="card-body">
                    <div class="text-muted small">Linhas com erro</div>
                    <div class="display-6 fw-semibold text-danger">{{ number_format($resultado['erros_total'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($resultado['criados'] > 0)
        <div class="alert alert-success">
            <i class="bi bi-check2-circle me-1"></i>
            {{ $resultado['criados'] }} paciente(s) já estão disponíveis no cadastro, agenda e financeiro.
        </div>
    @endif

    <div class="row g-3">
        @if ($resultado['ignorados_total'] > 0)
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-person-check me-2"></i>Ignorados
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 4rem">Linha</th>
                                    <th>Paciente</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($resultado['ignorados'] as $item)
                                    <tr>
                                        <td class="text-muted">{{ $item['linha'] }}</td>
                                        <td>{{ $item['nome'] }}</td>
                                        <td class="small text-muted">{{ $item['motivo'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($resultado['ignorados_total'] > count($resultado['ignorados']))
                        <div class="card-footer bg-white small text-muted">
                            Mostrando os primeiros {{ count($resultado['ignorados']) }} de {{ number_format($resultado['ignorados_total'], 0, ',', '.') }}.
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($resultado['erros_total'] > 0)
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-exclamation-octagon me-2"></i>Linhas com erro
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 4rem">Linha</th>
                                    <th>Paciente</th>
                                    <th>Mensagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($resultado['erros'] as $item)
                                    <tr>
                                        <td class="text-muted">{{ $item['linha'] }}</td>
                                        <td>{{ $item['nome'] }}</td>
                                        <td class="small text-muted">{{ $item['mensagem'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($resultado['erros_total'] > count($resultado['erros']))
                        <div class="card-footer bg-white small text-muted">
                            Mostrando os primeiros {{ count($resultado['erros']) }} de {{ number_format($resultado['erros_total'], 0, ',', '.') }}.
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($resultado['ignorados_total'] === 0 && $resultado['erros_total'] === 0)
        <div class="alert alert-info mt-3 mb-0">
            Todas as linhas da planilha foram importadas.
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2 mt-4">
        <a href="{{ route('pacientes.importar.create') }}" class="btn btn-primary">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Importar outra planilha
        </a>
        <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary">Ver pacientes</a>
    </div>
@endsection
