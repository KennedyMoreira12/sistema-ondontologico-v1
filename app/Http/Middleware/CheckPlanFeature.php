<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Libera uma rota apenas se o plano em vigor incluir o recurso.
 *
 * O recurso vem da ASSINATURA ATIVA, nunca de uma coluna `plan_id` na
 * clínica: é o que faz uma assinatura vencida perder acesso no mesmo minuto.
 *
 * Uso: ->middleware('plan:whatsapp')
 */
class CheckPlanFeature
{
    public function __construct(protected TenantManager $tenants) {}

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $clinic = $this->tenants->require();
        $clinic->loadMissing('latestSubscription.plan.features');

        $subscription = $clinic->latestSubscription;

        if (! $subscription?->isActive()) {
            return $this->deny($request, 'Sua assinatura não está ativa.', 'subscription');
        }

        foreach ($features as $feature) {
            if (! $subscription->hasFeature($feature)) {
                return $this->deny(
                    $request,
                    'O recurso '.$this->featureLabel($feature).' não está disponível no seu plano.',
                    $feature,
                );
            }
        }

        return $next($request);
    }

    /**
     * O motivo e o recurso são parâmetros separados de propósito: quem consome a
     * resposta em JSON precisa saber QUAL recurso faltou para oferecer a
     * assinatura certa, e um motivo que já é frase completa não serve como
     * identificador.
     */
    protected function deny(Request $request, string $message, ?string $feature = null): Response
    {
        /**
         * Resposta JSON em vez de `abort(403, [...])`: o segundo argumento de
         * `abort()` é a mensagem da exceção, e uma `HttpException` com array
         * estoura em TypeError — o sintoma era 500 em rota de API em vez de 403.
         */
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'feature' => $feature,
            ], 403);
        }

        return redirect()
            ->route('billing.index')
            ->with('warning', $message);
    }

    protected function featureLabel(string $feature): string
    {
        return config('tenancy.features.'.$feature, $feature);
    }
}
