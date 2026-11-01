@extends('layouts.app')

@section('title', 'Nova nota fiscal')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Nova nota fiscal</h1>
            <p class="text-muted small mb-0">O rascunho não é transmitido. Confira os dados antes de emitir.</p>
        </div>
        <a href="{{ route('fiscal.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Corrija os campos abaixo.</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('fiscal.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2"></i>Tomador</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="paciente_id" class="form-label">Paciente</label>
                                <select id="paciente_id" name="paciente_id" class="form-select">
                                    <option value="">Sem paciente vinculado</option>
                                    @foreach ($pacientes as $paciente)
                                        <option value="{{ $paciente->id }}"
                                            @selected((string) old('paciente_id', $pacienteSelecionado) === (string) $paciente->id)>
                                            {{ $paciente->nome }}{{ $paciente->cpf ? ' — '.$paciente->cpf : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="consulta_id" class="form-label">Agendamento</label>
                                <select id="consulta_id" name="consulta_id" class="form-select">
                                    <option value="">Sem agendamento</option>
                                    @foreach ($agendamentos as $agendamento)
                                        <option value="{{ $agendamento->id }}" @selected((string) old('consulta_id') === (string) $agendamento->id)>
                                            #{{ $agendamento->id }} · {{ $agendamento->paciente?->nome }} ·
                                            {{ $agendamento->inicio?->format('d/m/Y H:i') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <p class="text-muted small mt-3 mb-3">
                            Os campos de tomador abaixo são opcionais: em branco, a nota usa nome, CPF, e-mail e endereço do paciente.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="tomador_nome" class="form-label">Nome / razão social</label>
                                <input type="text" id="tomador_nome" name="tomador_nome" class="form-control" value="{{ old('tomador_nome') }}">
                            </div>
                            <div class="col-md-3">
                                <label for="tomador_documento" class="form-label">CPF / CNPJ</label>
                                <input type="text" id="tomador_documento" name="tomador_documento" class="form-control" value="{{ old('tomador_documento') }}">
                            </div>
                            <div class="col-md-3">
                                <label for="tomador_cep" class="form-label">CEP</label>
                                <input type="text" id="tomador_cep" name="tomador_cep" class="form-control" value="{{ old('tomador_cep') }}">
                            </div>
                            <div class="col-md-7">
                                <label for="tomador_email" class="form-label">E-mail</label>
                                <input type="email" id="tomador_email" name="tomador_email" class="form-control" value="{{ old('tomador_email') }}">
                            </div>
                            <div class="col-md-5">
                                <label for="tomador_endereco" class="form-label">Endereço</label>
                                <input type="text" id="tomador_endereco" name="tomador_endereco" class="form-control" value="{{ old('tomador_endereco') }}">
                            </div>
                            <div class="col-md-8">
                                <label for="tomador_cidade" class="form-label">Cidade</label>
                                <input type="text" id="tomador_cidade" name="tomador_cidade" class="form-control" value="{{ old('tomador_cidade') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="tomador_uf" class="form-label">UF</label>
                                <input type="text" id="tomador_uf" name="tomador_uf" class="form-control text-uppercase" maxlength="2" value="{{ old('tomador_uf') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard2-pulse me-2"></i>Serviço</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="codigo_servico" class="form-label">Código do serviço</label>
                                <input type="text" id="codigo_servico" name="codigo_servico" class="form-control"
                                    value="{{ old('codigo_servico', config('fiscal.codigo_servico')) }}">
                            </div>
                            <div class="col-md-8">
                                <label for="descricao_servico" class="form-label">Descrição</label>
                                <input type="text" id="descricao_servico" name="descricao_servico" class="form-control"
                                    value="{{ old('descricao_servico', config('fiscal.descricao_servico')) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Valores</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo</label>
                            <select id="tipo" name="tipo" class="form-select" required>
                                <option value="nfse" @selected(old('tipo', 'nfse') === 'nfse')>NFS-e (serviço)</option>
                                <option value="nfe" @selected(old('tipo') === 'nfe')>NF-e</option>
                                <option value="nfce" @selected(old('tipo') === 'nfce')>NFC-e</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="serie" class="form-label">Série</label>
                            <input type="text" id="serie" name="serie" class="form-control"
                                value="{{ old('serie', config('fiscal.serie')) }}">
                        </div>
                        <div class="mb-3">
                            <label for="valor_total" class="form-label">Valor do serviço</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" step="0.01" min="0.01" id="valor_total" name="valor_total"
                                    class="form-control" value="{{ old('valor_total') }}" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="valor_desconto" class="form-label">Desconto</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" step="0.01" min="0" id="valor_desconto" name="valor_desconto"
                                    class="form-control" value="{{ old('valor_desconto', '0.00') }}">
                            </div>
                        </div>
                        <div class="mb-0">
                            <label for="aliquota_iss" class="form-label">Alíquota ISS (%)</label>
                            <input type="number" step="0.01" min="0" max="100" id="aliquota_iss" name="aliquota_iss"
                                class="form-control" value="{{ old('aliquota_iss', config('fiscal.aliquota_iss')) }}">
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check2 me-1"></i>Salvar rascunho
                        </button>
                        <p class="text-muted small mb-0 mt-3">
                            A emissão usa o perfil fiscal da clínica. Se faltar CNPJ, razão social ou inscrição municipal,
                            o sistema avisa antes de chamar o provedor.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
