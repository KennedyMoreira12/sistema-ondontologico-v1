<?php

namespace App\Http\Middleware;

use App\Models\Central\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Áreas exclusivas do super admin (hoje, a prospecção comercial).
 *
 * O `auth:admin` só garante que é alguém da equipe da plataforma; o papel
 * `support` também entra por ele. Aqui o acesso é restrito a `super_admin`, e a
 * checagem roda na rota (e não só na tela), para que nenhum endpoint fique
 * aberto por esquecimento de um `@if` no Blade.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        abort_unless(
            $admin instanceof AdminUser && $admin->isSuperAdmin(),
            403,
            'Apenas o super admin pode acessar a prospecção comercial.'
        );

        return $next($request);
    }
}
