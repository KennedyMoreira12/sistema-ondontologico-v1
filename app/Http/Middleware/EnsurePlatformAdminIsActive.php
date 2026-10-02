<?php

namespace App\Http\Middleware;

use App\Models\Central\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Derruba a sessão de quem foi desativado no meio do uso.
 *
 * Desligar um acesso no banco só impede o próximo login; sem este middleware a
 * sessão já aberta continuaria operando clínicas de clientes até expirar. Roda
 * depois do `auth:admin`, então `$request->user()` já é o admin.
 *
 * O destino é a tela única de login: é de lá que a equipe volta a entrar.
 */
class EnsurePlatformAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (! $admin instanceof AdminUser) {
            return redirect()->route('login');
        }

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('warning', 'Este acesso foi desativado.');
        }

        return $next($request);
    }
}
