<?php

namespace App\Http\Middleware;

use App\Support\IntegrationAbilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que o token de integração tenha a ability da rota sendo chamada.
 *
 * Roda depois do `AuthenticateIntegration`, que é quem coloca o token
 * autenticado na requisição. Token sem `abilities` vale para tudo, que é o
 * retrocomportamento de quando a coluna não era aplicada.
 */
class EnsureIntegrationAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->attributes->get(AuthenticateIntegration::REQUEST_ATTRIBUTE);

        if ($token === null) {
            // Não deveria acontecer: este middleware só faz sentido depois do
            // AuthenticateIntegration. Recusar é melhor que liberar sem token.
            return response()->json(['message' => 'Token de integração não autenticado.'], 401);
        }

        $required = IntegrationAbilities::requiredFor($request->route()?->getName());

        if ($required !== null && ! $token->hasAbility($required)) {
            return response()->json([
                'message' => "Este token não tem permissão para [{$required}].",
            ], 403);
        }

        return $next($request);
    }
}
