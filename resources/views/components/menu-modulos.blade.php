@use('App\Services\Tenant\VerticalManager')
@use('App\Support\Modulos\CatalogoModulos')

{{--
    Menu de módulos da clínica, gerado a partir do catálogo.

    Uma única fonte de verdade: o catálogo diz o nome do módulo, o ícone, a
    seção do menu, para onde ele leva e em que situação está para a vertical
    da clínica. O menu não repete nada disso — se o catálogo mudar, o menu
    muda junto.

    Módulo `planejado` leva para a tela padrão "Funcionalidade planejada —
    ainda não disponível para uso." em vez de prometer uma tela que ainda não
    foi entregue.
--}}

@php
    $catalogo = app(CatalogoModulos::class);
    $verticals = app(VerticalManager::class);

    // Só UM item do menu fica marcado como atual. O módulo geral ("Pacientes")
    // vem antes dos módulos de tela do paciente na ordem do catálogo, então o
    // último que casa é o mais específico — e é ele que fica marcado.
    $moduloAtivo = null;

    foreach ($catalogo->todos() as $slug => $modulo) {
        if (request()->routeIs($modulo['padrao'])) {
            $moduloAtivo = $slug;
        }
    }

    if (request()->routeIs('modulos.planejado')) {
        $moduloAtivo = request()->route('modulo');
    }
@endphp

@foreach ([CatalogoModulos::GRUPO_PRINCIPAL => 'Principal', CatalogoModulos::GRUPO_GESTAO => 'Gestão'] as $grupo => $tituloGrupo)
    @php
        $itens = $catalogo->doGrupo($grupo);
    @endphp

    @if (count($itens) > 0)
        <div class="nav-section-title">{{ $tituloGrupo }}</div>

        @if ($grupo === CatalogoModulos::GRUPO_PRINCIPAL && (string) ($slot ?? '') !== '')
            {{ $slot }}
        @endif

        @foreach ($itens as $slug => $modulo)
            @continue(! $verticals->permite($slug))
            @continue(isset($modulo['permissao']) && ! auth()->user()?->can($modulo['permissao']))

            @php
                $planejado = $catalogo->situacaoParaClinica($slug) === CatalogoModulos::PLANEJADO;
                $ativo = $slug === $moduloAtivo;
            @endphp

            <a href="{{ $planejado ? route('modulos.planejado', $slug) : route($modulo['rota']) }}"
               class="nav-link {{ $ativo ? 'active' : '' }}"
               @if ($ativo) aria-current="page" @endif>
                <i class="bi {{ $modulo['icone'] }}"></i> {{ $modulo['titulo'] }}
            </a>
        @endforeach
    @endif
@endforeach
