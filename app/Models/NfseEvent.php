<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nfse_id',
    'event_type',
    'source',
    'event_id',
    'payload_sanitized',
    'processed_at',
])]
class NfseEvent extends TenantModel
{
    protected $table = 'nfse_events';

    public $timestamps = ['created_at'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload_sanitized' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function nfse(): BelongsTo
    {
        return $this->belongsTo(Nfse::class);
    }
}
