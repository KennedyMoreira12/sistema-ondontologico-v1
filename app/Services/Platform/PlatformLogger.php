<?php

namespace App\Services\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Clinic;
use App\Models\Central\PlatformAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Ações do painel da empresa, gravadas no banco central.
 *
 * O espelho de App\Services\AuditLogger, que registra no `audit_logs` do banco da
 * clínica. As duas tabelas não podem ser confundidas: quem opera a plataforma
 * precisa saber que uma clínica foi suspensa mesmo que a clínica não registre
 * nada disso, porque a clinic não tem como saber que existe.
 */
class PlatformLogger
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function log(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        ?array $metadata = null,
        ?Clinic $clinic = null,
        ?AdminUser $admin = null,
    ): PlatformAuditLog {
        return PlatformAuditLog::create([
            'admin_user_id' => $admin?->id ?? self::currentAdminId(),
            'clinic_id' => $clinic?->id,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /**
     * Ações em lote (migrar toda a base, por exemplo) usam o admin logado como
     * responsável e o erro fica no `metadata`, não num log separado.
     */
    public static function logBatch(string $action, string $entity, ?int $entityId, ?array $metadata = null): ?PlatformAuditLog
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin instanceof AdminUser) {
            return null;
        }

        return self::log($action, $entity, $entityId, $metadata, admin: $admin);
    }

    protected static function currentAdminId(): ?int
    {
        $admin = Auth::guard('admin')->user();

        return $admin instanceof AdminUser ? $admin->id : null;
    }
}
