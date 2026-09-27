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
            return $this->deny($request, 'Sua assinatura não está ativa.');
        }

        foreach ($features as $feature) {
            if (! $subscription->hasFeature($feature)) {
                return $this->deny($request, $this->featureLabel($feature));
            }
        }

        return $next($request);
    }

    protected function deny(Request $request, string $feature): Response
    {
        $message = "O recurso {$feature} não está disponível no seu plano.";

        if ($request->expectsJson()) {
            abort(403, ['message' => $message, 'feature' => $feature]);
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
