@extends('layouts.app')

@section('title', 'Histórico de laser')

@section('page-title', 'Regiões a Laser')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">Histórico de aplicações</h1>
            <p class="text-muted small mb-0">
                Sessões de laser já realizadas. O número da sessão é calculado por paciente e região. Nenhuma cobrança é gerada aqui.
            </p>
        </div>
    </div>

    @include('laser._tabs')

    @if ($regioes->isEmpty())
        <div class="alert alert-warning">
            Cadastre uma região antes de registrar aplicações.
            <a href="{{ route('regioes_laser.index') }}" class="alert-link">Ir para regiões</a>.
        </div>
    @endif

    @can('regioes_laser.create')
        @if ($regioes->isNotEmpty())
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-plus-circle me-2"></i>Registrar aplicação
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('regioes_laser.aplicacoes.store') }}">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="paciente_id" class="form-label">Paciente *</label>
                                <select id="paciente_id" name="paciente_id"
                                        class="form-select @error('paciente_id') is-invalid @enderror" required>
                                    <option value="">Selecione…</option>
                                    @foreach ($pacientes as $paciente)
                                        <option value="{{ $paciente->id }}" @selected((int) old('paciente_id', request('paciente')) === $paciente->id)>
                                            {{ $paciente->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('paciente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-3">
                                <label for="regiao_id" class="form-label">Região *</label>
                                <select id="regiao_id" name="regiao_id"
                                        class="form-select @error('regiao_id') is-invalid @enderror" required>
                                    <option value="">Selecione…</option>
                                    @foreach ($regioes as $regiao)
                                        <option value="{{ $regiao->id }}" @selected((int) old('regiao_id') === $regiao->id)>
                                            {{ $regiao->nome }}{{ $regiao->is_active ? '' : ' (inativa)' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('regiao_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-3">
                                <label for="pacote_id" class="form-label">Pacote (opcional)</label>
                                <select id="pacote_id" name="pacote_id"
                                        class="form-select @error('pacote_id') is-invalid @enderror">
                                    <option value="">Sessão avulsa</option>
                                    @foreach ($pacotes as $pacote)
                                        <option value="{{ $pacote->id }}"
                                                data-regiao="{{ $pacote->regiao_id }}"
                                                @selected((int) old('pacote_id') === $pacote->id)>
                                            {{ $pacote->regiao?->nome }} — {{ $pacote->nome }} ({{ $pacote->total_sessoes }} sessões)
                                        </option>
                                    @endforeach
                                </select>
                                @error('pacote_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-2">
                                <label for="data" class="form-label">Data *</label>
                                <input type="date" id="data" name="data"
                                       value="{{ old('data', now()->toDateString()) }}"
                                       class="form-control @error('data') is-invalid @enderror" required>
                                @error('data')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-4">
                                <label for="profissional_id" class="form-label">Profissional</label>
                                <select id="profissional_id" name="profissional_id"
                                        class="form-select @error('profissional_id') is-invalid @enderror">
                                    <option value="">Não informado</option>
                                    @foreach ($profissionais as $profissional)
                                        <option value="{{ $profissional->id }}" @selected((int) old('profissional_id') === $profissional->id)>
                                            {{ $profissional->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('profissional_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="observacoes" class="form-label">Observações</label>
                                <input type="text" id="observacoes" name="observacoes" maxlength="2000"
                                       value="{{ old('observacoes') }}"
                                       class="form-control @error('observacoes') is-invalid @enderror"
                                       placeholder="Parâmetros, intercorrências, orientações…">
                                @error('observacoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-check-lg me-1"></i>Registrar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('regioes_laser.aplicacoes.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="filtro_paciente" class="form-label small text-muted mb-1">Paciente</label>
                    <select id="filtro_paciente" name="paciente" class="form-select form-select-sm">
                        <option value="">Todos os pacientes</option>
                        @foreach ($pacientes as $paciente)
                            <option value="{{ $paciente->id }}" @selected((int) request('paciente') === $paciente->id)>
                                {{ $paciente->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="filtro_regiao" class="form-label small text-muted mb-1">Região</label>
                    <select id="filtro_regiao" name="regiao" class="form-select form-select-sm">
                        <option value="">Todas as regiões</option>
                        @foreach ($regioes as $regiao)
                            <option value="{{ $regiao->id }}" @selected((int) request('regiao') === $regiao->id)>
                                {{ $regiao->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="filtro_de" class="form-label small text-muted mb-1">De</label>
                    <input type="date" id="filtro_de" name="de" value="{{ request('de') }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-2">
                    <label for="filtro_ate" class="form-label small text-muted mb-1">Até</label>
                    <input type="date" id="filtro_ate" name="ate" value="{{ request('ate') }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Filtrar">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('regioes_laser.aplicacoes.index') }}" class="btn btn-sm btn-outline-secondary" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Paciente</th>
                        <th>Região</th>
                        <th>Pacote</th>
                        <th class="text-center">Sessão</th>
                        <th>Profissional</th>
                        <th>Observações</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($aplicacoes as $aplicacao)
                        <tr>
                            <td class="text-nowrap">{{ $aplicacao->data?->format('d/m/Y') }}</td>
                            <td>
                                @if ($aplicacao->paciente)
                                    <a href="{{ route('pacientes.prontuario', $aplicacao->paciente) }}">{{ $aplicacao->paciente->nome }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $aplicacao->regiao?->nome ?? '—' }}</td>
                            <td>
                                @if ($aplicacao->pacote)
                                    {{ $aplicacao->pacote->nome }}
                                    @if ($aplicacao->excedeOPacote())
                                        <span class="badge text-bg-warning ms-1" title="A sessão passou do total previsto no pacote">
                                            além do pacote
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted">Avulsa</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-light border">nº {{ $aplicacao->numero_sessao }}</span>
                                @if ($aplicacao->pacote)
                                    <span class="text-muted small">de {{ $aplicacao->pacote->total_sessoes }}</span>
                                @endif
                            </td>
                            <td>{{ $aplicacao->profissional?->nome ?? '—' }}</td>
                            <td class="text-muted small">{{ $aplicacao->observacoes ?: '—' }}</td>
                            <td class="text-end text-nowrap">
                                @can('regioes_laser.delete')
                                    <form method="POST" action="{{ route('regioes_laser.aplicacoes.destroy', $aplicacao) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Excluir esta aplicação do histórico?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i><span class="visually-hidden">Excluir aplicação</span>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Nenhuma aplicação encontrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            {{ $aplicacoes->links() }}
        </div>
    </div>

    @if ($regioes->isNotEmpty())
        <script>
            // O pacote precisa pertencer à região escolhida: as opções que não
            // casam ficam desabilitadas, e a seleção é limpa quando deixa de
            // valer. A validação de verdade continua no servidor.
            (function () {
                var regiao = document.getElementById('regiao_id');
                var pacote = document.getElementById('pacote_id');

                if (!regiao || !pacote) {
                    return;
                }

                function sincronizar() {
                    var escolhida = regiao.value;

                    Array.prototype.forEach.call(pacote.options, function (opcao) {
                        if (!opcao.value) {
                            return;
                        }

                        var pertence = opcao.dataset.regiao === escolhida;
                        opcao.disabled = !pertence;
                        opcao.hidden = !pertence;

                        if (!pertence && opcao.selected) {
                            pacote.value = '';
                        }
                    });
                }

                regiao.addEventListener('change', sincronizar);
                sincronizar();
            })();
        </script>
    @endif
@endsection
