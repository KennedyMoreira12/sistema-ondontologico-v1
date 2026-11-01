@extends('layouts.app')

@section('title', 'Importar pacientes')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Importar pacientes</h1>
            <p class="text-muted small mb-0">Envie a planilha e confira as colunas antes de gravar no cadastro.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-1-circle me-2"></i>Escolha a planilha
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('pacientes.importar.preview') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="arquivo" class="form-label">Arquivo (.xlsx ou .csv)</label>
                            <input type="file" id="arquivo" name="arquivo" accept=".xlsx,.csv,.txt"
                                   class="form-control @error('arquivo') is-invalid @enderror" required>
                            @error('arquivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Até 5 MB e 3.000 linhas por importação. A primeira linha da planilha precisa ter o nome das colunas.
                                Se o arquivo for .xls antigo, abra no Excel e salve como .xlsx.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Enviar e conferir colunas
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6"><i class="bi bi-signpost-split me-2"></i>Como funciona</h2>
                    <ol class="small text-muted mb-0 ps-3">
                        <li class="mb-1">Envie a planilha do jeito que ela está.</li>
                        <li class="mb-1">Na próxima tela, diga qual coluna corresponde a cada campo do cadastro.</li>
                        <li>Confira o resumo: criados, ignorados (já cadastrados) e erros.</li>
                    </ol>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6">Colunas reconhecidas automaticamente</h2>
                    <p class="small text-muted mb-2">Se a planilha usar estes nomes, o mapeamento já vem preenchido:</p>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach (['Nome', 'CPF', 'Nascimento', 'Sexo', 'E-mail', 'Celular', 'Telefone', 'CEP', 'Endereço', 'Número', 'Bairro', 'Cidade', 'UF', 'Responsável', 'Observações'] as $coluna)
                            <span class="badge text-bg-light border">{{ $coluna }}</span>
                        @endforeach
                    </div>

                    <hr>

                    <p class="small text-muted mb-2">
                        Datas podem vir como <strong>dd/mm/aaaa</strong> ou data do Excel. CPF, CEP e telefone podem vir com pontuação:
                        o sistema guarda só os números.
                    </p>

                    <a href="{{ route('pacientes.importar.modelo') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download me-1"></i>Baixar modelo CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
