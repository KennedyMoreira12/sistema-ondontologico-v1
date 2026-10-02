{{--
    Campos de recurso: um checkbox `habilitado` por recurso de
    `tenancy.features` e um campo `limite_*` para a contagem.

    O limite guardado no banco é o nome SEM o prefixo `limite_`: é assim que
    `Plan::featureLimits()` lê, e é o que um job de consumo vai consumir.
--}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6 mb-3">Identificação</h2>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Nome</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $plano?->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="slug" class="form-label">Slug</label>
                <input id="slug" type="text" class="form-control @error('slug') is-invalid @enderror" name="slug" value="{{ old('slug', $plano?->slug) }}" required>
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Descrição</label>
                <textarea id="description" class="form-control" name="description" rows="2">{{ old('description', $plano?->description) }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6 mb-3">Preço</h2>

        <div class="row g-3">
            <div class="col-md-4">
                <label for="price_monthly" class="form-label">Preço mensal (R$)</label>
                <input id="price_monthly" type="number" step="0.01" min="0" class="form-control @error('price_monthly') is-invalid @enderror" name="price_monthly" value="{{ old('price_monthly', $plano?->price_monthly ?? '0.00') }}" required>
                @error('price_monthly')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="trial_days" class="form-label">Dias de avaliação</label>
                <input id="trial_days" type="number" min="0" max="365" class="form-control" name="trial_days" value="{{ old('trial_days', $plano?->trial_days ?? 14) }}" required>
            </div>
            <div class="col-md-4">
                <label for="sort_order" class="form-label">Ordem</label>
                <input id="sort_order" type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order', $plano?->sort_order ?? 1) }}" required>
            </div>
            <div class="col-md-6 d-flex gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $plano?->is_active ?? true))>
                    <label class="form-check-label" for="is_active">À venda</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="is_featured" @checked(old('is_featured', $plano?->is_featured ?? false))>
                    <label class="form-check-label" for="is_featured">Destaque</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6 mb-3">Recursos</h2>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;">Inclui</th>
                        <th>Recurso</th>
                        <th style="width: 260px;">Limite mensal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recursoLabels as $chave => $rotulo)
                        @php
                            $limitesAtuais = $plano?->featureLimits($chave) ?? [];
                            $habilitado = (bool) old("features.$chave.habilitado", isset($plano) && $plano->hasFeature($chave));
                        @endphp
                        <tr>
                            <td>
                                <input class="form-check-input" type="checkbox" name="features[{{ $chave }}][habilitado]" value="1"
                                       id="feature_{{ $chave }}" @checked($habilitado)>
                            </td>
                            <td>
                                <label for="feature_{{ $chave }}">{{ $rotulo }}</label>
                                <div class="text-muted small font-monospace">{{ $chave }}</div>
                            </td>
                            <td>
                                @forelse ($limitesPorRecurso[$chave] ?? [] as $limite => $titulo)
                                    <div class="mb-1">
                                        <label class="form-label small text-muted mb-0" for="limite_{{ $chave }}_{{ $limite }}">{{ $titulo }}</label>
                                        <input id="limite_{{ $chave }}_{{ $limite }}" type="number" min="0" class="form-control form-control-sm"
                                               name="features[{{ $chave }}][limite_{{ $limite }}]"
                                               value="{{ old("features.$chave.limite_$limite", $limitesAtuais[$limite] ?? '') }}"
                                               placeholder="sem contagem">
                                    </div>
                                @empty
                                    <span class="text-muted small">sem contagem</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @error('features.*')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar plano</button>
    <a href="{{ route('painel.planos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>
