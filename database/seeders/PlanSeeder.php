<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

/**
 * Planos comerciais do SaaS. Os limites ficam em `plan_features.limits` (json),
 * nunca em código — mudar o que cada plano inclui é uma decisão de negócio e
 * deve ser um UPDATE no banco, não um deploy.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Sistema de gestão odontológica para a clínica começar a informatizar.',
                'price_monthly' => 149.00,
                'trial_days' => 14,
                'is_featured' => false,
                'sort_order' => 1,
                'features' => [
                    'ai_agent' => [],
                    'scheduled_posts' => ['posts_per_month' => 15],
                    'instagram' => [],
                    'facebook' => [],
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Com agente de IA e automação de posts para Instagram e Facebook.',
                'price_monthly' => 299.00,
                'trial_days' => 14,
                'is_featured' => true,
                'sort_order' => 2,
                'features' => [
                    'ai_agent' => ['ai_messages_per_month' => 2000],
                    'scheduled_posts' => ['posts_per_month' => 50],
                    'instagram' => [],
                    'facebook' => [],
                    'appointment_ai' => [],
                ],
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Tudo do Pro, mais WhatsApp integrado e integrações externas.',
                'price_monthly' => 450.00,
                'trial_days' => 14,
                'is_featured' => false,
                'sort_order' => 3,
                'features' => [
                    'ai_agent' => ['ai_messages_per_month' => 10000],
                    'whatsapp' => [],
                    'instagram' => [],
                    'facebook' => [],
                    'scheduled_posts' => ['posts_per_month' => 100],
                    'appointment_ai' => [],
                    'external_api' => [],
                    'advanced_automations' => [],
                    'priority_support' => [],
                ],
            ],
        ];

        foreach ($plans as $attributes) {
            $features = $attributes['features'];
            unset($attributes['features']);

            $plan = Plan::updateOrCreate(['slug' => $attributes['slug']], [
                ...$attributes,
                'is_active' => true,
            ]);

            $plan->features()->delete();

            foreach ($features as $feature => $limits) {
                $plan->features()->create([
                    'feature' => $feature,
                    'limits' => $limits === [] ? null : $limits,
                ]);
            }
        }

        $this->command?->info(count($plans).' planos criados.');
    }
}
