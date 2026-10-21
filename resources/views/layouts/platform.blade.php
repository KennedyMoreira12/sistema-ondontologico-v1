<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Painel da plataforma') — {{ config('app.name', 'LJ SmartClinic') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="sco-layout sco-platform">
        <aside class="sco-sidebar" id="scoSidebar">
            <div class="sidebar-brand">
                <span class="sco-mark"><i class="bi bi-buildings"></i></span>
                <span class="brand-name">
                    Plataforma
                    <span class="brand-tagline">LJ Automação</span>
                </span>
            </div>

            <nav class="flex-grow-1">
                <div class="nav-section-title">Operação</div>
                <a href="{{ route('painel.dashboard') }}" class="nav-link {{ request()->routeIs('painel.dashboard') ? 'active' : '' }}" title="Visão geral">
                    <i class="bi bi-speedometer2"></i>
                </a>
                <a href="{{ route('painel.clinicas.index') }}" class="nav-link {{ request()->routeIs('painel.clinicas.*') ? 'active' : '' }}" title="Clínicas">
                    <i class="bi bi-hospital"></i>
                </a>
                <a href="{{ route('painel.assinaturas.index') }}" class="nav-link {{ request()->routeIs('painel.assinaturas.*') ? 'active' : '' }}" title="Assinaturas">
                    <i class="bi bi-receipt"></i>
                </a>

                <div class="nav-section-title">Comercial</div>
                <a href="{{ route('painel.planos.index') }}" class="nav-link {{ request()->routeIs('painel.planos.*') ? 'active' : '' }}" title="Planos">
                    <i class="bi bi-tags"></i>
                </a>
                @if (auth()->user()->isSuperAdmin())
                    <a href="{{ route('painel.prospeccao.index') }}" class="nav-link {{ request()->routeIs('painel.prospeccao.*') ? 'active' : '' }}" title="Prospecção">
                        <i class="bi bi-funnel"></i>
                    </a>
                @endif

                @if (auth()->user()->isSuperAdmin())
                    <div class="nav-section-title">Marketing</div>
                    <a href="{{ route('painel.social.index') }}" class="nav-link {{ request()->routeIs('painel.social.*') ? 'active' : '' }}" title="Redes sociais">
                        <i class="bi bi-instagram"></i>
                    </a>
                @endif

                <div class="nav-section-title">Governança</div>
                <a href="{{ route('painel.auditoria.index') }}" class="nav-link {{ request()->routeIs('painel.auditoria.*') ? 'active' : '' }}" title="Auditoria">
                    <i class="bi bi-list-check"></i>
                </a>
            </nav>

            <div class="sidebar-footer">{{ config('app.name', 'LJ SmartClinic') }}</div>
        </aside>

        <div class="sco-main">
            <header class="sco-topbar">
<div class="d-flex align-items-center gap-2">
                    <!-- Botao mobile (sempre visivel em telas pequenas) -->
                    <button class="btn btn-light d-lg-none" type="button" data-sidebar-toggle onclick="document.getElementById('scoSidebar').classList.toggle('show')" aria-label="Abrir menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <!-- Botao desktop para recolher/expandir -->
                    <button class="btn btn-light d-lg-inline-block sidebar-toggle" type="button" aria-label="Recolher menu" id="sidebarToggle">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </button>
                    <div class="sco-topbar-title">@yield('page-title', 'Painel da plataforma')</div>
</div>

                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="sco-mark" style="width: 1.6rem; height: 1.6rem; font-size: 0.8rem; border-radius: 0.4rem;"><i class="bi bi-person-badge"></i></span>
                        <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text small text-muted">
                                {{ auth()->user()->isSuperAdmin() ? 'Super admin' : 'Suporte' }}
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('painel.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sair</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="sco-content">
                @foreach (['success', 'error', 'warning'] as $chave)
                    @if (session($chave))
                        <div class="alert alert-{{ $chave === 'warning' ? 'warning' : $chave }} alert-dismissible fade show" role="alert">
                            {{ session($chave) }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    @endif
                @endforeach

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const scoSidebar = document.getElementById('scoSidebar');
        
        // Check saved state or default to expanded
        const savedState = localStorage.getItem('scoSidebarState');
        const isCollapsed = savedState === 'collapsed';
        
        if (isCollapsed) {
            scoSidebar.classList.add('sco-collapsed');
            document.body.classList.add('sco-main-collapsed');
        }
        
        // Toggle functionality
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                const isCurrentlyCollapsed = scoSidebar.classList.contains('sco-collapsed');
                if (isCurrentlyCollapsed) {
                    scoSidebar.classList.remove('sco-collapsed');
                    document.body.classList.remove('sco-main-collapsed');
                    localStorage.setItem('scoSidebarState', 'expanded');
                } else {
                    scoSidebar.classList.add('sco-collapsed');
                    document.body.classList.add('sco-main-collapsed');
                    localStorage.setItem('scoSidebarState', 'collapsed');
                }
            });
        }
        
        // Also handle the mobile show toggle
        const mobileToggles = document.querySelectorAll('[data-sidebar-toggle]');
        mobileToggles.forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const isCollapsed = scoSidebar.classList.contains('sco-collapsed');
                if (!isCollapsed) {
                    scoSidebar.classList.toggle('show');
                }
            });
        });
    </script>

@stack('scripts')
</body>
</html>
