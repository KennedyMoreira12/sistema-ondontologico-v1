<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'clinic_id', 'plan_id', 'status', 'started_at', 'trial_ends_at',
    'current_period_start', 'current_period_end', 'grace_ends_at',
    'cancelled_at', 'ended_at', 'billing_provider',
    'external_subscription_id', 'external_customer_id', 'metadata',
])]
class Subscription extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, config('tenancy.accessible_subscription_states'), true);
    }

    public function isOnGracePeriod(): bool
    {
        return $this->status === 'past_due'
            && ($this->grace_ends_at === null || $this->grace_ends_at->isFuture());
    }

    public function hasFeature(string $feature): bool
    {
        return $this->isActive() && $this->plan?->hasFeature($feature) === true;
    }

    public function isExpired(): bool
    {
        return $this->current_period_end !== null && $this->current_period_end->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->current_period_end === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->current_period_end->startOfDay(), false);
    }

    public function statusLabel(): string
    {
        return config('tenancy.subscription_states.'.$this->status, $this->status);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('status', config('tenancy.accessible_subscription_states'));
    }

    public function history(): HasMany
    {
        return $this->hasMany(self::class, 'clinic_id', 'clinic_id');
    }
}
