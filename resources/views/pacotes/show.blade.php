@extends('layouts.app')

@section('title', 'Pacote de sessões')

@section('page-title', 'Pacote de sessões')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.pacotes.index', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">{{ $pacote->nome }}</h1>
                <p class="text-muted small mb-0">{{ $paciente->nome }}</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Saldo do pacote</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">Sessões usadas</dt>
                        <dd class="col-6 text-end">{{ $pacote->sessoes_usadas }}</dd>

                        <dt class="col-6">Total contratado</dt>
                        <dd class="col-6 text-end">{{ $pacote->total_sessoes }}</dd>

                        <dt class="col-6">Saldo</dt>
                        <dd class="col-6 text-end"><strong>{{ $pacote->saldo() }}</strong></dd>

                        <dt class="col-6">Valor da sessão</dt>
                        <dd class="col-6 text-end">{{ number_format((float) $pacote->valor_sessao, 2, ',', '.') }}</dd>

                        <dt class="col-6">Valor total</dt>
                        <dd class="col-6 text-end">{{ number_format((float) $pacote->valor_total, 2, ',', '.') }}</dd>

                        <dt class="col-6">Validade</dt>
                        <dd class="col-6 text-end">{{ $pacote->validade_em?->format('d/m/Y') ?? '—' }}</dd>

                        <dt class="col-6">Status</dt>
                        <dd class="col-6 text-end">
                            @if ($pacote->status === 'ativo')
                                <span class="badge text-bg-success">Ativo</span>
                            @elseif ($pacote->status === 'concluido')
                                <span class="badge text-bg-secondary">Concluído</span>
                            @else
                                <span class="badge text-bg-danger">Cancelado</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            @can('pacotes.update')
                @if ($pacote->status === 'ativo')
                    <div class="card shadow-sm">
                        <div class="card-header bg-white fw-semibold">Consumir sessão</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('pacientes.pacotes.consumir', [$paciente, $pacote]) }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="data" class="form-label">Data</label>
                                    <input type="date" id="data" name="data" class="form-control @error('data') is-invalid @enderror" value="{{ old('data', now()->toDateString()) }}">
                                    @error('data')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="observacoes" class="form-label">Observações</label>
                                    <textarea id="observacoes" name="observacoes" rows="2" class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes') }}</textarea>
                                    @error('observacoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="btn btn-primary w-100" @disabled($pacote->saldo() <= 0)>
                                    <i class="bi bi-check-lg me-1"></i>Consumir sessão
                                </button>
                            </form>

                            <hr>

                            <form method="POST" action="{{ route('pacientes.pacotes.destroy', [$paciente, $pacote]) }}"
                                onsubmit="return confirm('Cancelar este pacote? As sessões já consumidas continuam no extrato.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-x-circle me-1"></i>Cancelar pacote
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Extrato de sessões consumidas</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Profissional</th>
                                <th>Observações</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($consumos as $consumo)
                                <tr>
                                    <td>{{ $consumo->data->format('d/m/Y') }}</td>
                                    <td>{{ $consumo->profissional?->nome ?? 'Não informado' }}</td>
                                    <td>{{ $consumo->observacoes ?? '—' }}</td>
                                    <td class="text-end">{{ number_format((float) $consumo->valor, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Nenhuma sessão consumida ainda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $consumos->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
