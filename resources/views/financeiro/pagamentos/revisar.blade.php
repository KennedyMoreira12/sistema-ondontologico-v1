@extends('layouts.app')

@section('title', 'Revisar vínculo do pagamento')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('financeiro.pagamentos.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Revisar vínculo do pagamento</h1>
            <p class="text-muted small mb-0">O vínculo não altera o valor nem a data; só diz de quem é o recebimento.</p>
        </div>
    </div>

    @include('financeiro.partials.nav')

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Lançamento #{{ $pagamento->id }}</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Data</dt>
                        <dd class="col-7">{{ $pagamento->data?->format('d/m/Y') ?? '—' }}</dd>
                        <dt class="col-5">Valor</dt>
                        <dd class="col-7 fw-bold">R$ {{ number_format((float) $pagamento->valor, 2, ',', '.') }}</dd>
                        <dt class="col-5">Forma</dt>
                        <dd class="col-7">{{ $pagamento->paymentMethod?->label ?? '—' }}</dd>
                        <dt class="col-5">Serviço</dt>
                        <dd class="col-7">{{ $pagamento->servico ?: '—' }}</dd>
                        <dt class="col-5">Observações</dt>
                        <dd class="col-7">{{ $pagamento->observacoes ?: '—' }}</dd>
                        <dt class="col-5">Paciente atual</dt>
                        <dd class="col-7">{{ $pagamento->paciente?->nome ?? 'Não vinculado' }}</dd>
                    </dl>

                    @if ($pagamento->paciente_id)
                        <form method="POST" action="{{ route('financeiro.pagamentos.desvincular', $pagamento) }}" class="mt-3"
                              onsubmit="return confirm('Remover o vínculo deste pagamento? O lançamento continua no caixa.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Desfazer vínculo</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-magic me-2"></i>Sugestões pelo nome
                </div>
                <div class="card-body">
                    @if ($nomeNaPlanilha !== null)
                        <p class="small text-muted mb-2">Nome reconhecido no lançamento: <strong>{{ $nomeNaPlanilha }}</strong></p>
                    @else
                        <p class="small text-muted mb-2">Não foi possível extrair um nome do lançamento. Use a busca abaixo.</p>
                    @endif

                    @forelse ($candidatos as $candidato)
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                            <div>
                                <div class="fw-semibold">{{ $candidato['paciente']->nome }}</div>
                                <div class="text-muted small">Semelhança: {{ number_format($candidato['score'] * 100, 1, ',', '.') }}%</div>
                            </div>
                            @if (! $pagamento->paciente_id)
                                <form method="POST" action="{{ route('financeiro.pagamentos.vincular', $pagamento) }}">
                                    @csrf
                                    <input type="hidden" name="paciente_id" value="{{ $candidato['paciente']->id }}">
                                    <button class="btn btn-sm btn-success"><i class="bi bi-link-45deg me-1"></i>Vincular</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Nenhuma sugestão acima do limite de segurança. Busque o paciente manualmente.</p>
                    @endforelse
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-search me-2"></i>Buscar paciente</div>
                <div class="card-body">
                    <form method="GET" action="{{ route('financeiro.pagamentos.revisar', $pagamento) }}" class="row g-2 align-items-end mb-3">
                        <div class="col-md-9">
                            <label for="paciente" class="form-label">Nome</label>
                            <input type="search" id="paciente" name="paciente" value="{{ $busca }}" class="form-control" placeholder="Digite parte do nome...">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Buscar</button>
                        </div>
                    </form>

                    @if ($busca !== '' && $pacientes->isEmpty())
                        <p class="text-muted small mb-0">Nenhum paciente encontrado com esse nome.</p>
                    @elseif ($busca === '')
                        <p class="text-muted small mb-0">Digite o nome para listar os pacientes. As sugestões acima já vêm prontas quando o nome foi reconhecido.</p>
                    @else
                        <div class="list-group">
                            @foreach ($pacientes as $paciente)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $paciente->nome }} <span class="text-muted small">({{ $paciente->status }})</span></span>
                                    @if (! $pagamento->paciente_id)
                                        <form method="POST" action="{{ route('financeiro.pagamentos.vincular', $pagamento) }}">
                                            @csrf
                                            <input type="hidden" name="paciente_id" value="{{ $paciente->id }}">
                                            <button class="btn btn-sm btn-outline-success">Vincular</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
