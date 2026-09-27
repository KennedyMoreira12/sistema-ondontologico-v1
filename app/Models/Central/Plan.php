<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'description', 'price_monthly',
    'trial_days', 'is_active', 'is_featured', 'sort_order',
])]
class Plan extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return $this->features()->where('feature', $feature)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function featureLimits(string $feature): array
    {
        $limits = $this->features()->where('feature', $feature)->value('limits');

        return is_array($limits) ? $limits : (json_decode((string) $limits, true) ?: []);
    }

    /**
     * @return array<string, mixed>
     */
    public function featureMap(): array
    {
        return $this->features->pluck('limits', 'feature')->all();
    }

    public function monthlyPrice(): string
    {
        return 'R$ '.number_format((float) $this->price_monthly, 2, ',', '.');
    }
}
