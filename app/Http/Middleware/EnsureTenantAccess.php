<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Barra o painel de uma clínica cuja assinatura não está liberando acesso.
 *
 * Roda DEPOIS do ResolveTenant. Estados `past_due` e `suspended` deixam o
 * usuário entrar numa tela de aviso, com os recursos pagos desligados pelo
 * CheckPlanFeature — bloquear login inteiro deixaria a clínica sem como ver
 * a própria cobrança.
 */
class EnsureTenantAccess
{
    public function __construct(protected TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $clinic = $this->tenants->require();

        if ($clinic->isAccessible()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, [
                'message' => 'A assinatura desta clínica não está ativa.',
                'status' => $clinic->status,
            ]);
        }

        return redirect()
            ->route('dashboard')
            ->with('warning', 'Sua assinatura precisa ser renovada para continuar usando o sistema. Entre em contato com o suporte.');
    }
}
