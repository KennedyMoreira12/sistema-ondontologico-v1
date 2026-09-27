<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Consumo agregado por clínica e por mês. É o que permite descobrir quanto
 * cada clínica realmente custa (IA, mensagens, publicação) e sustentar a
 * cobrança por consumo em vez de "IA ilimitada".
 */
#[Fillable([
    'clinic_id', 'period', 'messages_inbound', 'messages_outbound',
    'ai_requests', 'ai_input_tokens', 'ai_output_tokens',
    'posts_published', 'n8n_executions', 'storage_bytes',
])]
class UsageMetric extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'date',
            'messages_inbound' => 'integer',
            'messages_outbound' => 'integer',
            'ai_requests' => 'integer',
            'ai_input_tokens' => 'integer',
            'ai_output_tokens' => 'integer',
            'posts_published' => 'integer',
            'n8n_executions' => 'integer',
            'storage_bytes' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public static function currentPeriod(): Carbon
    {
        return now()->startOfMonth();
    }

    public static function record(Clinic $clinic, string $counter, int $amount = 1): void
    {
        $clinic->usageMetrics()->firstOrCreate(
            ['period' => self::currentPeriod()],
        )->increment($counter, $amount);
    }
}
