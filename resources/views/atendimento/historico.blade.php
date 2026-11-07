@extends('layouts.app')

@section('title', 'Histórico do paciente')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">{{ $paciente->nome }}</h1>
                <p class="text-muted small mb-0">
                    @if ($paciente->celular || $paciente->phone)
                        {{ $paciente->celular ?: $paciente->phone }} ·
                    @endif
                    Histórico de atendimentos, sessões e pagamentos
                </p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person me-1"></i>Ficha do paciente
            </a>
            @can('agenda.view')
                <a href="{{ route('atendimentos.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-calendar-check me-1"></i>Atendimentos do dia
                </a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Consultas registradas</div>
                <div class="fs-4 fw-bold">{{ $resumo['consultas'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success"><div class="card-body">
                <div class="text-muted small">Presenças</div>
                <div class="fs-4 fw-bold text-success">{{ $resumo['atendidos'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger"><div class="card-body">
                <div class="text-muted small">Faltas</div>
                <div class="fs-4 fw-bold text-danger">{{ $resumo['faltas'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary"><div class="card-body">
                <div class="text-muted small">Pacotes ativos</div>
                <div class="fs-4 fw-bold text-primary">{{ $resumo['pacotes_ativos'] }}</div>
                <div class="text-muted small">{{ $resumo['sessoes_restantes'] }} sessões restantes</div>
            </div></div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar3 me-2"></i>Consultas e atendimentos</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Serviço</th>
                                <th>Profissional</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($consultas as $consulta)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $consulta->inicio->format('d/m/Y') }}</div>
                                        <div class="text-muted small">{{ $consulta->inicio->format('H:i') }}</div>
                                    </td>
                                    <td>
                                        {{ $consulta->servico ?: $consulta->titulo ?: '—' }}
                                        @if ($consulta->area)
                                            <div class="text-muted small">{{ $consulta->area }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $consulta->profissional?->nome ?? '—' }}</td>
                                    <td>
                                        <span class="badge" style="background: {{ $consulta->status?->cor ?? '#6c757d' }}">
                                            {{ $consulta->status?->label ?? '—' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma consulta registrada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $consultas->links() }}</div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Pagamentos vinculados</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Serviço</th>
                                <th>Forma</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pagamentos as $pagamento)
                                <tr>
                                    <td>{{ $pagamento->data?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $pagamento->servico ?: '—' }}</td>
                                    <td>{{ $pagamento->paymentMethod?->label ?? '—' }}</td>
                                    <td class="text-end fw-semibold">R$ {{ number_format((float) $pagamento->valor, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Nenhum pagamento vinculado a este paciente.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="text-muted small">
                        Total pago: <strong>R$ {{ number_format($resumo['total_pago'], 2, ',', '.') }}</strong>
                    </span>
                    <span class="text-muted small">
                        Lançamentos antigos sem vínculo aparecem em Financeiro → Pagamentos.
                    </span>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-box-seam me-2"></i>Pacotes de sessões</div>
                <div class="card-body">
                    @forelse ($pacotes as $pacote)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $pacote->nome }}</div>
                                    <div class="text-muted small">
                                        {{ $pacote->servico ?: 'Sem serviço informado' }}
                                        @if ($pacote->profissional)
                                            · {{ $pacote->profissional->nome }}
                                        @endif
                                    </div>
                                </div>
                                <span class="badge text-bg-{{ $pacote->estaAtivo() ? 'success' : 'secondary' }}">
                                    {{ ucfirst($pacote->status) }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between small text-muted mt-2">
                                <span>{{ $pacote->consumidas() }} de {{ $pacote->total_sessoes }} usadas</span>
                                <span class="fw-semibold text-body">{{ $pacote->restantes() }} restantes</span>
                            </div>
                            <div class="progress mt-1" role="progressbar" aria-valuenow="{{ $pacote->percentualUsado() }}" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
                                <div class="progress-bar" style="width: {{ $pacote->percentualUsado() }}%"></div>
                            </div>

                            @if ($pacote->validade_em)
                                <div class="text-muted small mt-2">Validade: {{ $pacote->validade_em->format('d/m/Y') }}</div>
                            @endif

                            @if ($pacote->consumos->isNotEmpty())
                                <details class="mt-2">
                                    <summary class="small text-primary" style="cursor: pointer;">Últimas sessões</summary>
                                    <ul class="list-unstyled small mt-2 mb-0">
                                        @foreach ($pacote->consumos->sortByDesc('data')->take(5) as $consumo)
                                            <li class="d-flex justify-content-between border-bottom py-1">
                                                <span>{{ $consumo->data?->format('d/m/Y') ?? '—' }}</span>
                                                <span>
                                                    @if ($consumo->reposicao)
                                                        <span class="badge text-bg-info">Reposição</span>
                                                    @else
                                                        Sessão
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">Nenhum pacote contratado.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
