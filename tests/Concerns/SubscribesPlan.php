<?php

namespace Tests\Concerns;

use App\Models\Central\Clinic;
use App\Models\Central\Plan;

/**
 * Assina um plano para a clínica de teste.
 *
 * Existe porque `CheckPlanFeature` decide pelo plano em vigor, e não por coluna
 * na clínica: um teste da API do WhatsApp que não assina nada recebe 403 "Sua
 * assinatura não está ativa", que não é o que o teste quer exercitar.
 *
 * O plano é criado sob demanda e reaproveitado, porque a assinatura é por
 * clínica e o plano é global.
 */
trait SubscribesPlan
{
    protected function assinarPlanoComWhatsapp(Clinic $clinica, string $recurso = 'whatsapp'): Clinic
    {
        $plano = Plan::query()->firstOrCreate(
            ['slug' => 'premium-teste'],
            [
                'name' => 'Premium Teste',
                'price_monthly' => 450,
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        if (! $plano->hasFeature($recurso)) {
            $plano->features()->create(['feature' => $recurso, 'limits' => []]);
        }

        $clinica->subscriptions()->create([
            'plan_id' => $plano->id,
            'status' => 'active',
            'started_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return $clinica->fresh(['latestSubscription.plan.features']);
    }
}
