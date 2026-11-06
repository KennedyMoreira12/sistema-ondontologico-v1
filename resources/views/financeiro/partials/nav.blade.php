{{-- Navegação comum do financeiro. Uma aba por fluxo, sem esconder os demais. --}}
<ul class="nav nav-pills gap-1 mb-4 flex-wrap">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('financeiro.index') ? 'active' : '' }}" href="{{ route('financeiro.index') }}">
            <i class="bi bi-arrow-down-circle me-1"></i>Contas a receber
        </a>
    </li>
    @can('financeiro.view')
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('financeiro.pagamentos.*') ? 'active' : '' }}" href="{{ route('financeiro.pagamentos.index') }}">
                <i class="bi bi-cash-coin me-1"></i>Pagamentos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('financeiro.contas-pagar.*') ? 'active' : '' }}" href="{{ route('financeiro.contas-pagar.index') }}">
                <i class="bi bi-arrow-up-circle me-1"></i>Contas a pagar
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('financeiro.fornecedores.*') ? 'active' : '' }}" href="{{ route('financeiro.fornecedores.index') }}">
                <i class="bi bi-truck me-1"></i>Fornecedores
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('financeiro.fluxo-caixa') ? 'active' : '' }}" href="{{ route('financeiro.fluxo-caixa') }}">
                <i class="bi bi-graph-up me-1"></i>Fluxo de caixa
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('financeiro.duplicatas.*') ? 'active' : '' }}" href="{{ route('financeiro.duplicatas.index') }}">
                <i class="bi bi-person-lines-fill me-1"></i>Nomes parecidos
            </a>
        </li>
    @endcan
</ul>
