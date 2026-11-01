@extends('layouts.platform')

@section('title', 'Importar contatos')
@section('page-title', 'Importar contatos')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">Importar contatos por planilha</h1>
            <p class="text-muted small mb-0">
                Envie um arquivo .xlsx ou .csv. Você confere uma prévia antes de confirmar — no envio, nada é gravado.
            </p>
        </div>
        <a href="{{ route('painel.prospeccao.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('painel.prospeccao.importar.store') }}" enctype="multipart/form-data" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="arquivo" class="form-label">Arquivo (.xlsx ou .csv)</label>
                            <input type="file" id="arquivo" name="arquivo" accept=".xlsx,.csv"
                                   class="form-control @error('arquivo') is-invalid @enderror" required>
                            @error('arquivo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Tamanho máximo: 5 MB.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Analisar arquivo
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Como funciona</h2>
                    <ol class="small text-muted ps-3 mb-3">
                        <li>Você envia o arquivo e uma <strong>prévia</strong> mostra o que seria importado.</li>
                        <li>Confira os novos, os já cadastrados e os possíveis duplicados.</li>
                        <li>Só ao <strong>confirmar</strong> os contatos entram na base.</li>
                    </ol>

                    <p class="small mb-1"><strong>Colunas reconhecidas</strong> (a do nome é obrigatória):</p>
                    <ul class="small text-muted mb-3 ps-3">
                        <li>Cidade</li>
                        <li>Clínica / profissional</li>
                        <li>Especialidade / tipo</li>
                        <li>Telefone publicado</li>
                        <li>Status do WhatsApp</li>
                        <li>Endereço</li>
                        <li>Fonte / abrir ficha</li>
                        <li>Status da prospecção</li>
                        <li>Data do contato</li>
                        <li>Observações</li>
                    </ul>

                    <p class="small text-muted mb-0">
                        Nada é enviado pelo WhatsApp, nenhuma automação é disparada e nenhum contato existente é apagado
                        ou sobrescrito.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
