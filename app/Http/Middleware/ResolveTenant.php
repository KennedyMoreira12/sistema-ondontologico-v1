<?php

namespace App\Http\Middleware;

use App\Exceptions\TenantConnectionInactive;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a clínica da requisição a partir do usuário AUTENTICADO e conecta no
 * banco dela.
 *
 * Este middleware é o que garante o isolamento: a clínica nunca vem da URL, do
 * body ou de um header — vem de `users.clinic_id`. Se não houver usuário
 * autenticado ou ele não pertencer a nenhuma clínica, a requisição morre aqui,
 * antes de qualquer model de tenant ser tocado.
 */
class ResolveTenant
{
    public function __construct(
        protected TenantManager $tenants,
        protected TenantDatabaseManager $databases,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        $clinic = $user->clinic;

        if ($clinic === null) {
            abort(403, 'Este usuário não está vinculado a nenhuma clínica.');
        }

        if (! $clinic->isAccessible()) {
            abort(403, 'A assinatura desta clínica não permite o acesso ao sistema.');
        }

        $this->tenants->set($clinic);

        try {
            $this->databases->connectFor($clinic);
        } catch (TenantConnectionInactive) {
            // 403 e não 500: a clínica foi desativada por decisão de quem opera,
            // então é estado de acesso, não erro do servidor.
            abort(403, 'O acesso a esta clínica está temporariamente desativado.');
        }

        return $next($request);
    }
}
