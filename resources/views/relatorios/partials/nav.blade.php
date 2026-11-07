{{-- Navegação comum dos relatórios. --}}
<ul class="nav nav-pills gap-1 mb-4 flex-wrap">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('relatorios.index') ? 'active' : '' }}" href="{{ route('relatorios.index') }}">
            <i class="bi bi-grid me-1"></i>Visão geral
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('relatorios.receitas') ? 'active' : '' }}" href="{{ route('relatorios.receitas') }}">
            <i class="bi bi-cash-stack me-1"></i>Receitas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('relatorios.presenca-faltas') ? 'active' : '' }}" href="{{ route('relatorios.presenca-faltas') }}">
            <i class="bi bi-calendar-check me-1"></i>Presença e faltas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('relatorios.pacotes') ? 'active' : '' }}" href="{{ route('relatorios.pacotes') }}">
            <i class="bi bi-box-seam me-1"></i>Pacotes e sessões
        </a>
    </li>
</ul>
