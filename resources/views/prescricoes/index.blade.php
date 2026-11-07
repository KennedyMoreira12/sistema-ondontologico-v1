@extends('layouts.app')

@section('title', 'Prescrições — ' . $paciente->nome)

@section('content')
    <style>
        @media print {
            .sco-sidebar, .sco-topbar, .no-print, .sco-content .card form { display: none !important; }
            .sco-main { margin-left: 0 !important; }
            .sco-content { padding: 0 !important; }
            .prescricao-print { page-break-inside: avoid; }
        }
    </style>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4 no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Prescrições de {{ $paciente->nome }}</h1>
                <p class="text-muted small mb-0">Receituário clínico do paciente</p>
            </div>
        </div>
        @if (vertical_permite('odontograma'))
            <a href="{{ route('pacientes.odontograma.index', $paciente) }}" class="btn btn-outline-primary">
                <i class="bi bi-grid-3x3-gap me-1"></i>Odontograma
            </a>
        @endif
    </div>

    <div class="row g-4 no-print">
        @can('prontuario.create')
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-1"></i>Nova prescrição</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pacientes.prescricoes.store', $paciente) }}">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="data" class="form-label">Data</label>
                                    <input type="date" id="data" name="data" class="form-control" value="{{ old('data', today()->toDateString()) }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="receita_profissional" class="form-label">Profissional</label>
                                    <input type="text" id="receita_profissional" class="form-control" value="{{ $profissionais->first()?->nome ?? '—' }}" disabled>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Medicamentos</label>
                                    <div id="itensReceita">

                                        <template id="templateItemReceita">
                                            <div class="linha-item border rounded p-2 mb-2 position-relative">
                                                <div class="row g-2">
                                                    <div class="col-12">
                                                        <input type="text" name="items[%i%][medicamento]" class="form-control form-control-sm" placeholder="Medicamento *">
                                                    </div>
                                                    <div class="col-6">
                                                        <input type="text" name="items[%i%][dose]" class="form-control form-control-sm" placeholder="Dose (ex.: 500mg)">
                                                    </div>
                                                    <div class="col-6">
                                                        <input type="text" name="items[%i%][frequencia]" class="form-control form-control-sm" placeholder="Frequência (ex.: 8/8h)">
                                                    </div>
                                                    <div class="col-6">
                                                        <input type="text" name="items[%i%][duracao]" class="form-control form-control-sm" placeholder="Duração (ex.: 5 dias)">
                                                    </div>
                                                    <div class="col-6">
                                                        <input type="text" name="items[%i%][observacao]" class="form-control form-control-sm" placeholder="Observação">
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-danger remover-item position-absolute top-0 end-0 mt-1 me-1" style="display:none" title="Remover">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="addItemReceita">
                                        <i class="bi bi-plus-lg me-1"></i>Adicionar medicamento
                                    </button>
                                </div>

                                <div class="col-12">
                                    <label for="observacoes" class="form-label">Observações</label>
                                    <textarea id="observacoes" name="observacoes" rows="2" class="form-control" placeholder="Posologia, orientações...">{{ old('observacoes') }}</textarea>
                                </div>

                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Registrar prescrição</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan

        <div class="col-lg-7">
            @forelse ($prescricoes as $prescricao)
                <div class="card shadow-sm mb-3 prescricao-print">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-semibold">Prescrição #{{ $prescricao->id }}</span>
                            <span class="text-muted small ms-2">{{ $prescricao->data?->translatedFormat('d/m/Y') }}</span>
                            <div class="text-muted small">{{ $prescricao->profissional?->nome ?? 'Profissional não informado' }}</div>
                        </div>
                        <div class="d-flex gap-2 no-print">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Imprimir"><i class="bi bi-printer"></i></button>
                            @can('prontuario.create')
                                <form method="POST" action="{{ route('pacientes.prescricoes.destroy', [$paciente, $prescricao]) }}" onsubmit="return confirm('Excluir esta prescrição?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($prescricao->items->isNotEmpty())
                            <table class="table table-sm align-middle mb-3">
                                <thead class="table-light">
                                    <tr>
                                        <th>Medicamento</th>
                                        <th>Dose</th>
                                        <th>Frequência</th>
                                        <th>Duração</th>
                                        <th>Observação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($prescricao->items as $item)
                                        <tr>
                                            <td class="fw-semibold">{{ $item->medicamento }}</td>
                                            <td>{{ $item->dose ?? '—' }}</td>
                                            <td>{{ $item->frequencia ?? '—' }}</td>
                                            <td>{{ $item->duracao ?? '—' }}</td>
                                            <td class="small text-muted">{{ $item->observacao ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-muted mb-3">Receita sem itens detalhados (2 vias).</p>
                        @endif
                        @if ($prescricao->observacoes)
                            <div class="alert alert-light border small mb-0">{{ $prescricao->observacoes }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="card shadow-sm">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-capsule fs-3"></i>
                        <p class="mb-0 mt-2">Nenhuma prescrição registrada para este paciente.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
    @can('prontuario.create')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const container = document.getElementById('itensReceita');
                const template = document.getElementById('templateItemReceita');
                let idx = 0;

                function updateRemoveButtons() {
                    container.querySelectorAll('.linha-item').forEach(function (linha, i) {
                        const btn = linha.querySelector('.remover-item');
                        if (btn) { btn.style.display = i === 0 ? 'none' : 'inline-block'; }
                    });
                }

                function addRow() {
                    const row = template.content.cloneNode(true);
                    row.querySelectorAll('[name]').forEach(function (el) {
                        el.setAttribute('name', el.getAttribute('name').replace('%i%', idx));
                    });
                    container.appendChild(row);
                    idx++;
                    updateRemoveButtons();
                }

                if (!container.querySelector('.linha-item')) { addRow(); }

                document.getElementById('addItemReceita').addEventListener('click', addRow);

                container.addEventListener('click', function (e) {
                    const btn = e.target.closest('.remover-item');
                    if (btn) { btn.closest('.linha-item').remove(); updateRemoveButtons(); }
                });
            });
        </script>
    @endcan
@endpush