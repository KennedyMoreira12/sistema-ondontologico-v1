<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') — @endif{{ config('app.name', 'LJ SmartClinic') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    {{-- A cor da clínica entra como acento: item ativo, títulos e marca. --}}
    <div class="sco-layout" style="--sco-accent: {{ clinic_primary_color() }}; --sco-accent-2: {{ clinic_secondary_color() }};">
        <aside class="sco-sidebar" id="scoSidebar">
            <div class="sidebar-brand">
                <span class="sco-mark"><i class="bi bi-hospital"></i></span>
                <span class="brand-name">
                    {{ $clinica?->name ?? config('app.name', 'LJ SmartClinic') }}
                </span>
            </div>

            <nav class="flex-grow-1">
                <div class="nav-section-title">Principal</div>
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="{{ route('pacientes.index') }}" class="nav-link {{ request()->routeIs('pacientes.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Pacientes
                </a>
                <a href="{{ route('agenda.index') }}" class="nav-link {{ request()->routeIs('agenda.*') ? 'active' : '' }}">
                    <i class="bi bi-calendar3"></i> Agenda
                </a>
                <a href="{{ route('dashboard') }}?modulo=prontuario" class="nav-link">
                    <i class="bi bi-journal-medical"></i> Prontuário
                </a>
                @if (vertical_permite('odontograma'))
                    <a href="{{ route('dashboard') }}?modulo=odontograma" class="nav-link">
                        <i class="bi bi-grid-3x3-gap"></i> Odontograma
                    </a>
                @endif
                <a href="{{ route('dashboard') }}?modulo=tratamentos" class="nav-link">
                    <i class="bi bi-clipboard2-pulse"></i> Tratamentos
                </a>

                <div class="nav-section-title">Gestão</div>
                <a href="{{ route('dashboard') }}?modulo=orcamentos" class="nav-link">
                    <i class="bi bi-file-earmark-text"></i> Orçamentos
                </a>
                <a href="{{ route('dashboard') }}?modulo=financeiro" class="nav-link">
                    <i class="bi bi-cash-stack"></i> Financeiro
                </a>
                <a href="{{ route('dashboard') }}?modulo=convenios" class="nav-link">
                    <i class="bi bi-shield-check"></i> Convênios
                </a>
                <a href="{{ route('dashboard') }}?modulo=profissionais" class="nav-link">
                    <i class="bi bi-person-badge"></i> Profissionais
                </a>
                <a href="{{ route('dashboard') }}?modulo=procedimentos" class="nav-link">
                    <i class="bi bi-journal-code"></i> Procedimentos
                </a>
                <a href="{{ route('dashboard') }}?modulo=estoque" class="nav-link">
                    <i class="bi bi-box-seam"></i> Estoque
                </a>
                <a href="{{ route('contratos.index') }}" class="nav-link {{ request()->routeIs('contratos.*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-check"></i> Contratos
                </a>
                @can('social.view')
                    <a href="{{ route('social.index') }}" class="nav-link {{ request()->routeIs('social.*') ? 'active' : '' }}">
                        <i class="bi bi-share"></i> Social
                    </a>
                @endcan
                <a href="{{ route('dashboard') }}?modulo=relatorios" class="nav-link">
                    <i class="bi bi-graph-up-arrow"></i> Relatórios
                </a>
                <a href="{{ route('dashboard') }}?modulo=documentos" class="nav-link">
                    <i class="bi bi-folder2-open"></i> Documentos
                </a>

                @can('admin.view')
                    <div class="nav-section-title">Administração</div>
                    <a href="{{ route('admin.usuarios.index') }}" class="nav-link {{ request()->routeIs('admin.usuarios*') ? 'active' : '' }}">
                        <i class="bi bi-person-gear"></i> Usuários
                    </a>
                    <a href="{{ route('admin.perfis.index') }}" class="nav-link {{ request()->routeIs('admin.perfis*') ? 'active' : '' }}">
                        <i class="bi bi-shield-lock"></i> Perfis
                    </a>
                    <a href="{{ route('admin.permissoes.index') }}" class="nav-link {{ request()->routeIs('admin.permissoes*') ? 'active' : '' }}">
                        <i class="bi bi-keys"></i> Permissões
                    </a>
                    <a href="{{ route('admin.clinica.edit') }}" class="nav-link {{ request()->routeIs('admin.clinica*') ? 'active' : '' }}">
                        <i class="bi bi-building"></i> Clínica
                    </a>
                    @can('auditoria.view')
                        <a href="{{ route('admin.auditoria.index') }}" class="nav-link {{ request()->routeIs('admin.auditoria*') ? 'active' : '' }}">
                            <i class="bi bi-list-check"></i> Auditoria
                        </a>
                    @endcan
                @endcan
            </nav>
        </aside>

        <div class="sco-main">
            <header class="sco-topbar">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-light d-lg-none" type="button" data-sidebar-toggle onclick="document.getElementById('scoSidebar').classList.toggle('show')" aria-label="Abrir menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="sco-topbar-title">@yield('page-title')</div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="sco-mark" style="width: 1.6rem; height: 1.6rem; font-size: 0.8rem; border-radius: 0.4rem;"><i class="bi bi-person"></i></span>
                        <span class="d-none d-md-inline">{{ Auth::user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text small text-muted">{{ Auth::user()->role?->label }}</span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Meu perfil</a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sair</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="sco-content">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                @endif

                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
