<?php

namespace App\Http\Middleware;

use App\Exceptions\TenantConnectionInactive;
use App\Models\Central\IntegrationToken;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica a API interna (n8n, webhooks da Meta) pelo token DA CLÍNICA e
 * conecta no banco dela.
 *
 * Diferente do painel web, aqui não existe sessão: a clínica vem do token. É
 * por isso que cada clínica tem o seu token — um token único global daria ao
 * n8n acesso ao banco de todas as clínicas, que é o pior vazamento possível
 * neste sistema.
 *
 * O token é guardado só como SHA-256, então a comparação é por hash e não
 * depende de o banco devolver o valor original.
 */
class AuthenticateIntegration
{
    /**
     * Chave em que o token autenticado fica disponível para o controller.
     */
    public const REQUEST_ATTRIBUTE = 'integration_token';

    public function __construct(
        protected TenantManager $tenants,
        protected TenantDatabaseManager $databases,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $provided = IntegrationToken::extractFromRequest($request);

        if ($provided === null) {
            return response()->json(['message' => 'Token de integração não informado.'], 401);
        }

        $token = IntegrationToken::query()
            ->where('token_hash', IntegrationToken::hashToken($provided))
            ->with('clinic.tenantConnection')
            ->first();

        if ($token === null || ! $token->isActive()) {
            return response()->json(['message' => 'Token de integração inválido.'], 401);
        }

        $clinic = $token->clinic;

        if ($clinic === null || ! $clinic->isAccessible()) {
            return response()->json(['message' => 'A clínica deste token não está com acesso liberado.'], 403);
        }

        $this->tenants->set($clinic);

        try {
            $this->databases->connectFor($clinic);
        } catch (TenantConnectionInactive) {
            // O token continua válido; quem está desligado é o acesso ao banco.
            // 403 deixa isso claro para o n8n, que não deve re-tentar em loop.
            return response()->json(['message' => 'O acesso ao banco desta clínica está desativado.'], 403);
        }

        $token->markUsed($request->ip());

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $token);

        return $next($request);
    }
}
