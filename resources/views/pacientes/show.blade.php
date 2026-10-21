@extends('layouts.app')

@section('title', $paciente->nome)

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">
                    {{ $paciente->nome }}
                    @if ($paciente->status === 'ativo')
                        <span class="badge bg-success ms-1">Ativo</span>
                    @else
                        <span class="badge bg-secondary ms-1">Inativo</span>
                    @endif
                </h1>
                <div class="text-muted small">
                    {{ $paciente->sexo === 'masculino' ? 'Masculino' : ($paciente->sexo === 'feminino' ? 'Feminino' : 'Sexo não informado') }}
                    • {{ $paciente->nascimento?->age ?? '—' }} anos
                    @if ($paciente->responsavel)
                        • Resp.: {{ $paciente->responsavel }}
                    @endif
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('odontograma.view')
                @if (vertical_permite('odontograma'))
                    <a href="{{ route('pacientes.odontograma.index', $paciente) }}" class="btn btn-outline-primary">
                        <i class="bi bi-grid-3x3-gap me-1"></i>Odontograma
                    </a>
                @endif
            @endcan
            @can('tratamentos.view')
                <a href="{{ route('pacientes.planos.index', $paciente) }}" class="btn btn-outline-primary">
                    <i class="bi bi-clipboard2-pulse me-1"></i>Planos
                </a>
            @endcan
            @can('orcamentos.view')
                <a href="{{ route('pacientes.orcamentos.index', $paciente) }}" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-text me-1"></i>Orçamentos
                </a>
            @endcan
            @can('prontuario.view')
                <a href="{{ route('pacientes.prescricoes.index', $paciente) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-capsule me-1"></i>Prescrições
                </a>
                <a href="{{ route('pacientes.prontuario', $paciente) }}" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-medical me-1"></i>Prontuário
                </a>
            @endcan
            @can('pacientes.update')
                <a href="{{ route('pacientes.edit', $paciente) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>
            @endcan
        </div>
    </div>

    @if ($paciente->alertas->where('is_active', true)->isNotEmpty())
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>Este paciente possui <strong>{{ $paciente->alertas->where('is_active', true)->count() }}</strong> alerta(s) clínico(s) ativo(s).</span>
        </div>
    @endif

    <ul class="nav nav-tabs" id="fichaTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aba-dados" type="button" role="tab">Dados</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-alertas" type="button" role="tab">Alertas clínicos</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-anamnese" type="button" role="tab">Anamnese</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-documentos" type="button" role="tab">Documentos</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-consultas" type="button" role="tab">Consultas</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aba-financeiro" type="button" role="tab">Planos e orçamentos</button>
        </li>
    </ul>

    <div class="tab-content pt-3">
        <div class="tab-pane fade show active" id="aba-dados" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white fw-semibold">Contato</div>
                        <ul class="list-group list-group-flush">
                            @php
                                $campos = [
                                    'E-mail' => $paciente->email,
                                    'Telefone' => $paciente->phone,
                                    'Celular' => $paciente->celular,
                                    'CPF' => $paciente->cpf,
                                    'RG' => $paciente->rg,
                                    'Nascimento' => $paciente->nascimento?->translatedFormat('d/m/Y'),
                                ];
                            @endphp
                            @foreach ($campos as $label => $valor)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="text-muted">{{ $label }}</span>
                                    <span>{{ $valor ?? '—' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white fw-semibold">Endereço</div>
                        <ul class="list-group list-group-flush">
                            @php
                                $endereco = trim(implode(', ', array_filter([
                                    $paciente->endereco,
                                    $paciente->numero,
                                    $paciente->complemento,
                                ])));
                            @endphp
                            <li class="list-group-item"><span class="text-muted d-block">Endereço</span>{{ $endereco ?: '—' }}</li>
                            <li class="list-group-item"><span class="text-muted d-block">Bairro</span>{{ $paciente->bairro ?? '—' }}</li>
                            <li class="list-group-item"><span class="text-muted d-block">Cidade / UF</span>{{ trim($paciente->cidade.' / '.$paciente->estado, ' /') ?: '—' }}</li>
                            <li class="list-group-item"><span class="text-muted d-block">CEP</span>{{ $paciente->cep ?? '—' }}</li>
                        </ul>
                    </div>
                </div>
                @if ($paciente->observacoes)
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white fw-semibold">Observações</div>
                            <div class="card-body">{{ $paciente->observacoes }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="tab-pane fade" id="aba-alertas" role="tabpanel">
            @can('alertas.create')
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <form method="POST" action="{{ route('pacientes.alertas.store', $paciente) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-3">
                                <label for="alerta_tipo" class="form-label">Tipo</label>
                                <select id="alerta_tipo" name="tipo" class="form-select" required>
                                    <option value="alergia">Alergia</option>
                                    <option value="medicamento">Medicamento</option>
                                    <option value="diabetes">Diabetes</option>
                                    <option value="hipertensao">Hipertensão</option>
                                    <option value="cardiopatia">Cardiopatia</option>
                                    <option value="gestante">Gestante</option>
                                    <option value="condicao">Outra condição</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label for="alerta_descricao" class="form-label">Descrição</label>
                                <input type="text" id="alerta_descricao" name="descricao" class="form-control" placeholder="Ex.: Alergia a penicilina" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card shadow-sm">
                <div class="card-body">
                    @forelse ($paciente->alertas as $alerta)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <span class="badge {{ $alerta->is_active ? 'bg-danger' : 'bg-secondary' }} text-uppercase">{{ $alerta->tipo }}</span>
                                <span class="ms-2">{{ $alerta->descricao }}</span>
                            </div>
                            @if ($alerta->is_active)
                                <form method="POST" action="{{ route('pacientes.alertas.destroy', [$paciente, $alerta]) }}" onsubmit="return confirm('Desativar este alerta?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Desativar"><i class="bi bi-check-circle"></i></button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">Nenhum alerta clínico registrado.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="aba-anamnese" role="tabpanel">
            @can('anamnese.create')
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">Nova anamnese</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pacientes.anamneses.store', $paciente) }}">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="titulo" class="form-label">Título</label>
                                    <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Ex.: Anamnese inicial" value="{{ old('titulo') }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="responsavel" class="form-label">Responsável pelas respostas</label>
                                    <input type="text" id="responsavel" name="responsavel" class="form-control" value="{{ old('responsavel') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Respostas (uma por linha, formato <code>pergunta: resposta</code>)</label>
                                    <textarea rows="5" name="respostas" class="form-control" placeholder="Já fez cirurgia: não&#10;Fuma: não&#10;Usa medicamento contínuo: sim (losartana)">{{ old('respostas') }}</textarea>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">Salvar anamnese</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            @forelse ($paciente->anamneses as $anamnese)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span class="fw-semibold">{{ $anamnese->titulo ?? 'Anamnese' }}</span>
                        <span class="text-muted small">{{ $anamnese->created_at->translatedFormat('d/m/Y H:i') }}</span>
                    </div>
                    <div class="card-body">
                        @if ($anamnese->responsavel)
                            <p class="text-muted small mb-2">Respondido por: <strong>{{ $anamnese->responsavel }}</strong></p>
                        @endif
                        @if (is_array($anamnese->respostas) && count($anamnese->respostas))
                            <ul class="mb-0">
                                @foreach ($anamnese->respostas as $pergunta => $resposta)
                                    <li><strong>{{ $pergunta }}:</strong> {{ is_array($resposta) ? implode(', ', $resposta) : $resposta }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-muted mb-0">Sem respostas registradas.</p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="card shadow-sm">
                    <div class="card-body text-center text-muted">Nenhuma anamnese registrada.</div>
                </div>
            @endforelse
        </div>

        <div class="tab-pane fade" id="aba-documentos" role="tabpanel">
            @can('documentos.create')
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">Anexar documento</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pacientes.documentos.store', $paciente) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="tipo" class="form-label">Tipo</label>
                                    <select id="tipo" name="tipo" class="form-select" required>
                                        <option value="raio-x">Raio-X</option>
                                        <option value="laudo">Laudo</option>
                                        <option value="exame">Exame</option>
                                        <option value="receita">Receita</option>
                                        <option value="atestado">Atestado</option>
                                        <option value="contrato">Contrato</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="titulo" class="form-label">Título</label>
                                    <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Ex.: Radiografia panorâmica">
                                </div>
                                <div class="col-md-4">
                                    <label for="arquivo" class="form-label">Arquivo (PDF, imagens, docs até 10MB)</label>
                                    <input type="file" id="arquivo" name="arquivo" class="form-control" required>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Anexar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Título</th>
                                <th>Enviado por</th>
                                <th>Data</th>
                                <th>Tamanho</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paciente->documentos as $documento)
                                <tr>
                                    <td><span class="badge bg-light text-dark border text-uppercase">{{ $documento->tipo }}</span></td>
                                    <td>{{ $documento->titulo ?? '—' }}</td>
                                    <td>{{ $documento->user?->name ?? '—' }}</td>
                                    <td>{{ $documento->created_at->translatedFormat('d/m/Y') }}</td>
                                    <td>{{ number_format($documento->tamanho / 1024, 0, ',', '.') }} KB</td>
                                    <td class="text-end">
                                        <a href="{{ route('pacientes.documentos.download', [$paciente, $documento]) }}" class="btn btn-sm btn-outline-primary" title="Baixar"><i class="bi bi-download"></i></a>
                                        <form method="POST" action="{{ route('pacientes.documentos.destroy', [$paciente, $documento]) }}" class="d-inline" onsubmit="return confirm('Remover este documento?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Nenhum documento anexado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="aba-consultas" role="tabpanel">
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Profissional</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paciente->consultas as $consulta)
                                <tr>
                                    <td>{{ $consulta->inicio->translatedFormat('d/m/Y H:i') }}</td>
                                    <td>{{ $consulta->profissional?->nome ?? '—' }}</td>
                                    <td>
                                        <span class="badge" style="background-color: {{ $consulta->status?->cor ?? '#6c757d' }};">
                                            {{ $consulta->status?->label ?? '—' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Nenhuma consulta registrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="aba-financeiro" role="tabpanel">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Planos de tratamento</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Plano</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th class="text-end">Itens</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($planos as $plano)
                                <tr>
                                    <td>{{ $plano->id }}</td>
                                    <td>
                                        <a href="{{ route('pacientes.planos.show', [$paciente, $plano]) }}" class="text-decoration-none">
                                            {{ $plano->titulo }}
                                        </a>
                                    </td>
                                    <td>
                                        @php
                                            $cores = [
                                                'rascunho' => '#6c757d',
                                                'aprovado' => '#0d6efd',
                                                'em_andamento' => '#ffc107',
                                                'concluido' => '#198754',
                                                'cancelado' => '#dc3545',
                                            ];
                                            $labels = [
                                                'rascunho' => 'Rascunho',
                                                'aprovado' => 'Aprovado',
                                                'em_andamento' => 'Em andamento',
                                                'concluido' => 'Concluído',
                                                'cancelado' => 'Cancelado',
                                            ];
                                        @endphp
                                        <span class="badge" style="background-color: {{ $cores[$plano->status] ?? '#6c757d' }}">
                                            {{ $labels[$plano->status] ?? $plano->status }}
                                        </span>
                                    </td>
                                    <td>{{ $plano->created_at->translatedFormat('d/m/Y') }}</td>
                                    <td class="text-end">{{ $plano->items_count }}</td>
                                    <td class="text-end">R$ {{ number_format($plano->total, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Nenhum plano de tratamento.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Orçamentos</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Orçamento</th>
                                <th>Criado em</th>
                                <th>Validade</th>
                                <th>Status</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orcamentos as $orcamento)
                                <tr>
                                    <td>{{ $orcamento->id }}</td>
                                    <td>
                                        <a href="{{ route('pacientes.orcamentos.show', [$paciente, $orcamento]) }}" class="text-decoration-none">
                                            {{ $orcamento->titulo ?? 'Orçamento #'.$orcamento->id }}
                                        </a>
                                    </td>
                                    <td>{{ $orcamento->created_at->translatedFormat('d/m/Y') }}</td>
                                    <td>{{ $orcamento->validade ? $orcamento->validade->translatedFormat('d/m/Y') : '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ $orcamento->status ?? '—' }}</span></td>
                                    <td class="text-end">R$ {{ number_format($orcamento->total, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Nenhum orçamento.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection