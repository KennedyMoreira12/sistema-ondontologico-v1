@php
    $marca = $marcas->get($numero);
    $cor = $marca?->toothCondition?->cor ?? '#e9ecef';
    $ausente = $marca?->toothCondition?->codigo === 'ausente';
    $podeEditar = auth()->user()->hasPermission('odontograma.update');
@endphp

@if ($podeEditar)
    <button
        type="button"
        class="tooth {{ $ausente ? 'tooth-ausente' : '' }}"
        style="background: {{ $cor }};"
        title="Dente {{ $numero }}{{ $marca?->toothCondition ? ' — '.$marca->toothCondition->name : '' }}"
        data-tooth="{{ $numero }}"
        data-condicao-id="{{ $marca?->tooth_condition_id ?? '' }}"
        data-obs="{{ $marca?->observacao ?? '' }}"
    >{{ $ausente ? '✕' : $numero }}</button>
@else
    <span
        class="tooth tooth-readonly {{ $ausente ? 'tooth-ausente' : '' }}"
        style="background: {{ $cor }};"
        title="Dente {{ $numero }}{{ $marca?->toothCondition ? ' — '.$marca->toothCondition->name : '' }}"
    >{{ $ausente ? '✕' : $numero }}</span>
@endif