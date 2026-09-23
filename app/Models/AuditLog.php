<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'acao', 'tabela', 'registro_id', 'antes', 'depois', 'ip'])]
class AuditLog extends \Illuminate\Database\Eloquent\Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'depois' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}