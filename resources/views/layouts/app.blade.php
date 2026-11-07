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
    {{-- Atalho de teclado: pula o menu e vai direto ao conteúdo. --}}
    <a class="sco-skip-link" href="#scoConteudo">Pular para o conteúdo</a>

    {{-- A cor da clínica entra como acento: item ativo, títulos e marca. --}}
    <div class="sco-layout" style="--sco-accent: {{ clinic_primary_color() }}; --sco-accent-2: {{ clinic_secondary_color() }};">
        <aside class="sco-sidebar" id="scoSidebar">
            <div class="sidebar-brand">
                @if (clinic_logo_url())
                    <img src="{{ clinic_logo_url() }}" alt="" class="sco-logo">
                @else
                    <span class="sco-mark"><i class="bi bi-hospital"></i></span>
                @endif
                <span class="brand-name">
                    {{ $clinica?->name ?? config('app.name', 'LJ SmartClinic') }}
                </span>
                <button type="button" class="btn btn-light d-lg-none sco-sidebar-close" data-sidebar-close aria-label="Fechar menu">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <nav class="flex-grow-1" aria-label="Menu principal">
                {{-- O menu vem do catálogo de módulos: nome, ícone, seção e
                     situação por vertical ficam em um lugar só. --}}
                <x-menu-modulos>
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </x-menu-modulos>

                @can('social.view')
                    <a href="{{ route('social.index') }}" class="nav-link {{ request()->routeIs('social.*') ? 'active' : '' }}" @if (request()->routeIs('social.*')) aria-current="page" @endif>
                        <i class="bi bi-share"></i> Social
                    </a>
                @endcan

                @can('admin.view')
                    <div class="nav-section-title">Administração</div>
                    <a href="{{ route('admin.usuarios.index') }}" class="nav-link {{ request()->routeIs('admin.usuarios*') ? 'active' : '' }}" @if (request()->routeIs('admin.usuarios*')) aria-current="page" @endif>
                        <i class="bi bi-person-gear"></i> Usuários
                    </a>
                    <a href="{{ route('admin.perfis.index') }}" class="nav-link {{ request()->routeIs('admin.perfis*') ? 'active' : '' }}" @if (request()->routeIs('admin.perfis*')) aria-current="page" @endif>
                        <i class="bi bi-shield-lock"></i> Perfis
                    </a>
                    <a href="{{ route('admin.permissoes.index') }}" class="nav-link {{ request()->routeIs('admin.permissoes*') ? 'active' : '' }}" @if (request()->routeIs('admin.permissoes*')) aria-current="page" @endif>
                        <i class="bi bi-keys"></i> Permissões
                    </a>
                    <a href="{{ route('admin.clinica.edit') }}" class="nav-link {{ request()->routeIs('admin.clinica*') ? 'active' : '' }}" @if (request()->routeIs('admin.clinica*')) aria-current="page" @endif>
                        <i class="bi bi-building"></i> Clínica
                    </a>
                    @can('fiscal.view')
                        <a href="{{ route('admin.fiscal.index') }}" class="nav-link {{ request()->routeIs('admin.fiscal*') ? 'active' : '' }}" @if (request()->routeIs('admin.fiscal*')) aria-current="page" @endif>
                            <i class="bi bi-receipt"></i> Fiscal
                        </a>
                    @endcan
                    @can('auditoria.view')
                        <a href="{{ route('admin.auditoria.index') }}" class="nav-link {{ request()->routeIs('admin.auditoria*') ? 'active' : '' }}" @if (request()->routeIs('admin.auditoria*')) aria-current="page" @endif>
                            <i class="bi bi-list-check"></i> Auditoria
                        </a>
                    @endcan
                @endcan
            </nav>
        </aside>

        {{-- No celular, o menu abre por cima do conteúdo: a cortina escurece a
             página e some ao tocar fora do menu. --}}
        <div class="sco-backdrop" data-sidebar-backdrop hidden></div>

        <div class="sco-main">
            <header class="sco-topbar">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-light d-lg-none" type="button" data-sidebar-toggle aria-controls="scoSidebar" aria-expanded="false" aria-label="Abrir menu">
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

            <main class="sco-content" id="scoConteudo" tabindex="-1">
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

                @if (session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        {{ session('warning') }}
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

    {{-- Menu lateral no celular: abre, fecha, fecha ao tocar fora e devolve
         o foco. Sem isso o menu ficava preso aberto atrás do botão que o
         abre, e o teclado não tinha como sair dele. --}}
    <script>
        (function () {
            var sidebar = document.getElementById('scoSidebar');
            var toggle = document.querySelector('[data-sidebar-toggle]');

            if (!sidebar || !toggle) {
                return;
            }

            var backdrop = document.querySelector('[data-sidebar-backdrop]');
            var fecharBotao = document.querySelector('[data-sidebar-close]');

            function telaPequena() {
                return window.matchMedia('(max-width: 991.98px)').matches;
            }

            function abrir() {
                sidebar.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-label', 'Fechar menu');

                if (backdrop) {
                    backdrop.hidden = false;
                }
            }

            function fechar() {
                sidebar.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Abrir menu');

                if (backdrop) {
                    backdrop.hidden = true;
                }
            }

            toggle.addEventListener('click', function () {
                if (sidebar.classList.contains('show')) {
                    fechar();
                } else {
                    abrir();
                }
            });

            if (backdrop) {
                backdrop.addEventListener('click', fechar);
            }

            if (fecharBotao) {
                fecharBotao.addEventListener('click', fechar);
            }

            sidebar.querySelectorAll('a.nav-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (telaPequena()) {
                        fechar();
                    }
                });
            });

            document.addEventListener('keydown', function (evento) {
                if (evento.key === 'Escape' && sidebar.classList.contains('show')) {
                    fechar();
                    toggle.focus();
                }
            });

            window.addEventListener('resize', function () {
                if (!telaPequena()) {
                    fechar();
                }
            });
        })();
    </script>
</body>
</html>
