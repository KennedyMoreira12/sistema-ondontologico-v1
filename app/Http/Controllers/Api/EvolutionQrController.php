<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionQrController extends Controller
{
    public function show(Request $request)
    {

        $key = "evolution_qr_malheiro";
        $qr = Cache::get($key);

        if (! $qr) {
            $url = rtrim((string) config("services.evolution.url"), "/");
            $timeout = (int) config("services.evolution.timeout", 30);

            try {
                $resp = Http::timeout($timeout)
                    ->withHeaders(["apikey" => config("services.evolution.api_key")])
                    ->get("{$url}/instance/connect/malheiro");

                if (! $resp->successful()) {
                    Log::warning("Evolution QR fetch failed", ["status" => $resp->status(), "body" => $resp->body()]);
                    return response("QR indisponivel", 503);
                }

                $data = $resp->json();
                $qrData = $data["response"] ?? $data;
                $base64 = $qrData["base64"] ?? null;

                if (! $base64) {
                    return response("QR nao gerado", 404);
                }

                $qr = base64_decode(explode(",", $base64, 2)[1] ?? $base64);
                Cache::put("evolution_qr_malheiro", $qr, now()->addMinutes(2));
            } catch (\Throwable $e) {
                Log::error("Evolution QR error", ["err" => $e->getMessage()]);
                return response("Erro ao buscar QR", 500);
            }
        }

        return response($qr, 200, [
            "Content-Type" => "image/png",
            "Cache-Control" => "no-store, no-cache, must-revalidate",
        ]);
    }
}
