@extends('layouts.app')

@section('title', 'Conferir colunas da importação')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('pacientes.importar.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 mb-0">Conferir colunas</h1>
            <p class="text-muted small mb-0">
                {{ $estado['nome_original'] }} — {{ number_format($estado['total_linhas'], 0, ',', '.') }} linha(s) e
                {{ count($estado['cabecalho']) }} coluna(s) encontrada(s).
            </p>
        </div>
    </div>

    @if ($estado['truncada'])
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            A planilha tem mais linhas do que o limite. Serão importadas apenas as primeiras 3.000 linhas de dados.
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $rotulosPorColuna = [];

        foreach (\App\Services\Pacientes\ImportadorPacientes::CAMPOS as $campo => $definicao) {
            if (isset($estado['mapa'][$campo])) {
                $rotulosPorColuna[$estado['mapa'][$campo]] = $definicao['rotulo'];
            }
        }
    @endphp

    <form method="POST" action="{{ route('pacientes.importar.processar') }}">
        @csrf

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-2-circle me-2"></i>Diga qual coluna é cada campo
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    O mapeamento abaixo foi sugerido pelo nome das colunas. Confira e ajuste o que estiver errado.
                    Só o <strong>nome</strong> é obrigatório; os outros campos podem ficar como “não importar”.
                </p>

                @foreach ($grupos as $grupo => $campos)
                    <h2 class="h6 mt-4 mb-3">{{ $grupo }}</h2>
                    <div class="row g-3">
                        @foreach ($campos as $campo => $definicao)
                            <div class="col-md-6 col-xl-4">
                                <label for="mapa-{{ $campo }}" class="form-label">
                                    {{ $definicao['rotulo'] }}
                                    @if ($definicao['obrigatorio'] ?? false)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <select id="mapa-{{ $campo }}" name="mapa[{{ $campo }}]"
                                        class="form-select @error('mapa.'.$campo) is-invalid @enderror">
                                    <option value="">— Não importar —</option>
                                    @foreach ($estado['cabecalho'] as $indice => $rotulo)
                                        <option value="{{ $indice }}" @selected((string) old('mapa.'.$campo, $estado['mapa'][$campo] ?? '') === (string) $indice)>
                                            {{ $rotulo }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('mapa.'.$campo)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1"></i>Importar {{ number_format($estado['total_linhas'], 0, ',', '.') }} linha(s)
                </button>
                <a href="{{ route('pacientes.importar.create') }}" class="btn btn-outline-secondary">Trocar planilha</a>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('pacientes.importar.cancelar') }}" class="mb-3">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-link btn-sm text-danger px-0">
            <i class="bi bi-x-circle me-1"></i>Cancelar importação
        </button>
    </form>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-table me-2"></i>Prévia das primeiras linhas
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-muted" style="width: 3rem">#</th>
                        @foreach ($estado['cabecalho'] as $indice => $rotulo)
                            <th class="text-nowrap">
                                {{ $rotulo }}
                                @isset($rotulosPorColuna[$indice])
                                    <div class="small fw-normal text-muted">{{ $rotulosPorColuna[$indice] }}</div>
                                @endisset
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($estado['amostra'] as $posicao => $linha)
                        <tr>
                            <td class="text-muted">{{ $posicao + 2 }}</td>
                            @foreach ($estado['cabecalho'] as $indice => $rotulo)
                                <td class="text-nowrap">{{ $linha[$indice] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($estado['cabecalho']) + 1 }}" class="text-muted">Nenhuma linha para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($estado['total_linhas'] > count($estado['amostra']))
            <div class="card-footer bg-white small text-muted">
                Mostrando {{ count($estado['amostra']) }} de {{ number_format($estado['total_linhas'], 0, ',', '.') }} linha(s) de dados.
            </div>
        @endif
    </div>
@endsection
