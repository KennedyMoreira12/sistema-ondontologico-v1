@extends('layouts.app')

@section('title', 'Novo Post')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('social.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1 class="h4 mb-0">Novo Post</h1>
                <p class="text-muted small mb-0">Descreva a solicitação e o conteúdo será gerado para revisão.</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('social.store') }}" id="form-novo-post">
        @csrf

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square me-2"></i>Solicitação</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="tema" class="form-label">Tema *</label>
                                <input type="text" id="tema" name="tema" value="{{ old('tema') }}" class="form-control @error('tema') is-invalid @enderror" maxlength="255" required placeholder="Ex.: Importância da limpeza dental">
                                @error('tema')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="objetivo" class="form-label">Objetivo *</label>
                                <input type="text" id="objetivo" name="objetivo" value="{{ old('objetivo') }}" class="form-control @error('objetivo') is-invalid @enderror" maxlength="255" required placeholder="Ex.: Educar e atrair novos pacientes">
                                @error('objetivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="publico" class="form-label">Público *</label>
                                <input type="text" id="publico" name="publico" value="{{ old('publico') }}" class="form-control @error('publico') is-invalid @enderror" maxlength="191" required placeholder="Ex.: Adultos de 25 a 45 anos">
                                @error('publico')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="tipo" class="form-label">Tipo *</label>
                                <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                                    <option value="" disabled @selected(! old('tipo'))>Selecione…</option>
                                    @foreach (['educativo' => 'Educativo', 'institucional' => 'Institucional', 'promocional' => 'Promocional', 'informativo' => 'Informativo', 'sazonal' => 'Sazonal'] as $valor => $rotulo)
                                        <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                                @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="observacoes" class="form-label">Observações</label>
                                <textarea id="observacoes" name="observacoes" rows="3" class="form-control @error('observacoes') is-invalid @enderror" maxlength="2000" placeholder="Orientações extras, tom desejado, informações que não podem faltar…">{{ old('observacoes') }}</textarea>
                                @error('observacoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-image me-2"></i>Imagem</div>
                    <div class="card-body">
                        <label for="image_url" class="form-label">URL da imagem</label>
                        <input type="url" id="image_url" name="image_url" value="{{ old('image_url') }}" class="form-control @error('image_url') is-invalid @enderror" maxlength="1000" placeholder="https://…">
                        @error('image_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Opcional. Apenas uma URL pública começando com <code>https://</code>; nenhum arquivo é enviado.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-share me-2"></i>Plataformas *</div>
                    <div class="card-body">
                        @php $plataformasSelecionadas = old('platforms', []); @endphp
                        @foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook'] as $valor => $rotulo)
                            <div class="form-check">
                                <input type="checkbox" id="platform_{{ $valor }}" name="platforms[]" value="{{ $valor }}" class="form-check-input @error('platforms') is-invalid @enderror" @checked(in_array($valor, $plataformasSelecionadas, true))>
                                <label for="platform_{{ $valor }}" class="form-check-label">{{ $rotulo }}</label>
                            </div>
                        @endforeach
                        @error('platforms')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        <div class="form-text">Selecione ao menos uma.</div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-event me-2"></i>Data e hora</div>
                    <div class="card-body">
                        <label for="scheduled_at" class="form-label">Agendado para</label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at') }}" class="form-control @error('scheduled_at') is-invalid @enderror">
                        @error('scheduled_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Opcional. É apenas uma referência de agendamento; nada é publicado automaticamente.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('social.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" id="btn-gerar">
                <i class="bi bi-stars me-1"></i>Gerar conteúdo
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.getElementById('form-novo-post')?.addEventListener('submit', function () {
            const botao = document.getElementById('btn-gerar');
            if (botao) {
                botao.disabled = true;
                botao.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Gerando…';
            }
        });
    </script>
@endpush