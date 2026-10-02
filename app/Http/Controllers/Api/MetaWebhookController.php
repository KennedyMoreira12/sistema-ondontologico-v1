<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook da Meta (WhatsApp Cloud API).
 *
 * Rota pública: a Meta não tem token de clínica, então a confiança vem de
 * duas verificações independentes — o `hub.verify_token` no GET de
 * assinatura e o HMAC `X-Hub-Signature-256` sobre o corpo cru do POST.
 * Sem o App Secret configurado a rota recusa todo POST, porque uma rota
 * sem assinatura permitiria qualquer pessoa criar paciente e agendamento.
 */
class MetaWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        if ($request->query('hub_mode') !== 'subscribe') {
            return response('Forbidden', 403);
        }

        $expected = config('services.whatsapp_cloud.verify_token');

        if (! $expected || ! hash_equals((string) $expected, (string) $request->query('hub_verify_token'))) {
            return response('Forbidden', 403);
        }

        return response((string) $request->query('hub_challenge'), 200);
    }

    public function receive(Request $request): JsonResponse
    {
        $appSecret = config('services.meta.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $appSecret);

        if (! $appSecret || ! hash_equals($expected, $signature)) {
            Log::warning('Webhook da Meta com assinatura invalida.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'forbidden'], 403);
        }

        Log::info('Webhook da Meta recebido.', [
            'phone_number_id' => $request->json('entry.0.id'),
            'messages' => $request->json('entry.0.changes.0.value.messages'),
        ]);

        return response()->json(['status' => 'received'], 200);
    }
}
