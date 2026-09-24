<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.whatsapp.api_token');

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Integração WhatsApp não configurada.'], 503);
        }

        $provided = $request->bearerToken();

        if ($provided === null) {
            $header = (string) $request->header('X-WhatsApp-Token', '');

            $provided = str_starts_with($header, 'WhatsApp ') ? substr($header, 9) : ($header !== '' ? $header : null);
        }

        if ($provided === null || ! hash_equals($token, (string) $provided)) {
            return response()->json(['message' => 'Token de integração inválido.'], 401);
        }

        return $next($request);
    }
}
