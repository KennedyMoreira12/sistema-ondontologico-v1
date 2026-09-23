<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        string $acao,
        ?string $tabela = null,
        ?int $registroId = null,
        ?array $antes = null,
        ?array $depois = null,
        ?User $user = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id ?? auth()->id(),
            'acao' => $acao,
            'tabela' => $tabela,
            'registro_id' => $registroId,
            'antes' => $antes,
            'depois' => $depois,
            'ip' => Request::ip(),
        ]);
    }
}