@extends('layouts.app')

@section('title', 'Odontograma — ' . $paciente->nome)

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Odontograma de {{ $paciente->nome }}</h1>
                <p class="text-muted small mb-0">
                    {{ $odontogram->titulo }} • atualizado em {{ $odontogram->updated_at?->translatedFormat('d/m/Y H:i') }}
                </p>
            </div>
        </div>
        <a href="{{ route('pacientes.prontuario', $paciente) }}" class="btn btn-outline-primary">
            <i class="bi bi-journal-medical me-1"></i>Prontuário
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-semibold"><i class="bi bi-grid-3x3-gap me-1"></i>Coroa</span>
                    <span class="text-muted small">Clique num dente para registrar a condição</span>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 mb-4" id="legend">
                        @foreach ($condicoes as $condicao)
                            <span class="d-inline-flex align-items-center gap-1 small">
                                <span class="legend-dot" style="background: {{ $condicao->cor }};"></span>
                                {{ $condicao->name }}
                            </span>
                        @endforeach
                    </div>

                    @php
                        $linhaSuperior = array_merge(range(18, 11), range(21, 28));
                        $linhaInferior = array_merge(range(48, 41), range(31, 38));
                    @endphp

                    <div class="mb-4">
                        <div class="text-center text-uppercase text-muted small fw-semibold mb-2">Arcada superior</div>
                        <div class="arcada" id="arcada-superior">
                            @foreach ($linhaSuperior as $numero)
                                @include('odontograma.partials.tooth', ['numero' => $numero])
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="text-center text-uppercase text-muted small fw-semibold mb-2">Arcada inferior</div>
                        <div class="arcada" id="arcada-inferior">
                            @foreach ($linhaInferior as $numero)
                                @include('odontograma.partials.tooth', ['numero' => $numero])
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard2-pulse me-1"></i>Diagnóstico atual</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($marcas->sortBy('dente') as $marca)
                            <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <span class="fw-semibold">Dente {{ $marca->dente }}</span>
                                    <span class="badge ms-1" style="background: {{ $marca->toothCondition?->cor ?? '#6c757d' }}">
                                        {{ $marca->toothCondition?->name ?? '—' }}
                                    </span>
                                    @if ($marca->observacao)
                                        <div class="small text-muted">{{ $marca->observacao }}</div>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted py-4">Nenhuma condição registrada. Todos os dentes hígidos.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i>Legenda</div>
                <div class="card-body small text-muted">
                    Colorido = condição registrada. Cinza/­tracejado = dente hígido (sem registro).
                    Dentes "Ausente" aparecem com um X.
                </div>
            </div>
        </div>
    </div>

    @can('odontograma.update')
        @include('odontograma.partials.tooth-modal')
    @endcan
@endsection

@push('scripts')
    @can('odontograma.update')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const obsModal = new bootstrap.Modal(document.getElementById('toothModal'));

                document.querySelectorAll('[data-tooth]').forEach(function (botao) {
                    botao.addEventListener('click', function () {
                        const dente = botao.dataset.tooth;
                        const condicaoId = botao.dataset.condicaoId || '';
                        const obs = botao.dataset.obs || '';

                        const base = '{{ route('pacientes.odontograma.index', $paciente) }}';

                        document.getElementById('toothNumero').textContent = dente;
                        document.getElementById('toothDente').value = dente;
                        document.getElementById('toothCondicao').value = condicaoId;
                        document.getElementById('toothObservacao').value = obs;
                        document.getElementById('toothUpdateForm').action = base + '/dente/' + dente;
                        document.getElementById('toothResetForm').action = base + '/dente/' + dente;

                        obsModal.show();
                    });
                });
            });
        </script>
    @endcan
@endpush