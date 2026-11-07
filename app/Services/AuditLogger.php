<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Central\User;
use App\Services\Tenant\TenantManager;
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
        $antes = self::sanitize($antes);
        $depois = self::sanitize($depois);

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

    /**
     * Nunca registrar conteúdo clínico integral. Remove campos potencialmente sensíveis.
     */
    private static function sanitize(?array $dados): ?array
    {
        if ($dados === null) {
            return null;
        }

        $sensiveis = ['password', 'senha', 'token', 'secret', 'arquivo_conteudo', 'conteudo', 'anamnese_completa', 'prontuario'];

        $limpo = [];
        foreach ($dados as $chave => $valor) {
            if (in_array(strtolower((string) $chave), $sensiveis, true)) {
                continue;
            }
            $limpo[$chave] = $valor;
        }

        return $limpo;
    }
}
