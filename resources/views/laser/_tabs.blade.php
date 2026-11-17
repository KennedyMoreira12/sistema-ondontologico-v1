{{--
    Abas do módulo Regiões a Laser. As três telas compartilham o mesmo
    prefixo de rota (`regioes_laser.*`), então o menu marca o módulo como
    ativo em qualquer uma delas.
--}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('regioes_laser.index') ? 'active' : '' }}"
           href="{{ route('regioes_laser.index') }}"
           @if (request()->routeIs('regioes_laser.index')) aria-current="page" @endif>
            <i class="bi bi-body me-1"></i>Regiões
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('regioes_laser.pacotes.*') ? 'active' : '' }}"
           href="{{ route('regioes_laser.pacotes.index') }}"
           @if (request()->routeIs('regioes_laser.pacotes.*')) aria-current="page" @endif>
            <i class="bi bi-box-seam me-1"></i>Pacotes
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('regioes_laser.aplicacoes.*') ? 'active' : '' }}"
           href="{{ route('regioes_laser.aplicacoes.index') }}"
           @if (request()->routeIs('regioes_laser.aplicacoes.*')) aria-current="page" @endif>
            <i class="bi bi-clock-history me-1"></i>Histórico
        </a>
    </li>
</ul>
