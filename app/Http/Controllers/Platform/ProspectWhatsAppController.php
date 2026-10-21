<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\EvolutionIndisponivel;
use App\Http\Controllers\Controller;
use App\Services\Platform\Prospeccao\ProspectWhatsAppService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp EXCLUSIVO da prospecção comercial, pelo painel do super admin.
 *
 * Separado do `ClinicWhatsAppController`: este só fala da instância de
 * prospecção (nome fixo na config), nunca de uma clínica. Nenhum endereço, nome
 * de instância ou token vem do navegador — as ações não aceitam corpo algum, a
 * instância é a da config e o token é lido do banco central. A chave
 * administrativa da Evolution fica no backend e nunca entra na resposta.
 *
 * Nada aqui envia mensagem: apenas cria a instância, lê estado e, sob clique,
 * gera o QR.
 */
class ProspectWhatsAppController extends Controller
{
    public function __construct(protected ProspectWhatsAppService $whatsapp) {}

    /**
     * Estado apenas do que está gravado. Não consulta a Evolution nem gera QR,
     * então abrir a aba não mexe em instância conectada.
     */
    public function show(): JsonResponse
    {
        return response()->json($this->whatsapp->storedSummary($this->whatsapp->setting()));
    }

    /**
     * Cria (uma única vez) a instância de prospecção. Idempotente e sem número.
     */
    public function storeInstance(): JsonResponse
    {
        try {
            $resultado = $this->whatsapp->ensureInstance();
        } catch (Throwable $exception) {
            return $this->falha($exception);
        }

        return response()->json(
            $this->whatsapp->storedSummary($resultado['setting'])
            + ['criada' => $resultado['created']]
        );
    }

    /**
     * Consulta o estado real e atualiza o registro. Não gera QR.
     */
    public function refreshState(): JsonResponse
    {
        $setting = $this->whatsapp->setting();

        if ($setting === null) {
            return response()->json([
                'status' => ProspectWhatsAppService::STATUS_NOT_CONFIGURED,
                'configurado' => false,
                'mensagem' => 'A instância de prospecção ainda não foi configurada.',
            ], 409);
        }

        try {
            $this->whatsapp->refreshState($setting);
        } catch (Throwable $exception) {
            return $this->falha($exception, $setting->instance_name);
        }

        return response()->json($this->whatsapp->storedSummary($setting->fresh()));
    }

    /**
     * Gera o QR Code sob ação explícita do super admin. Se a instância já está
     * conectada, devolve o estado sem parear de novo.
     */
    public function qrcode(): JsonResponse
    {
        $setting = $this->whatsapp->setting();

        if ($setting === null) {
            return response()->json([
                'status' => ProspectWhatsAppService::STATUS_NOT_CONFIGURED,
                'configurado' => false,
                'mensagem' => 'Configure a instância de prospecção antes de gerar o QR Code.',
            ], 409);
        }

        try {
            $resultado = $this->whatsapp->qrcode($setting);
        } catch (Throwable $exception) {
            return $this->falha($exception, $setting->instance_name);
        }

        $payload = $this->whatsapp->storedSummary($setting->fresh()) + [
            'conectado' => $resultado['conectado'],
        ];

        if (! $resultado['conectado'] && ($resultado['qrcode'] ?? null) !== null) {
            $payload['qrcode'] = $resultado['qrcode'];
        } elseif ($resultado['conectado']) {
            $payload['mensagem'] = 'O WhatsApp de prospecção já está conectado.';
        }

        return response()->json($payload);
    }

    /**
     * Traduz a falha sem devolver corpo de erro da Evolution, token ou QR Code.
     */
    protected function falha(Throwable $exception, ?string $instanceName = null): JsonResponse
    {
        $contexto = ['instancia' => $instanceName];

        if ($exception instanceof EvolutionIndisponivel) {
            return response()->json([
                'status' => ProspectWhatsAppService::STATUS_ERROR,
                'mensagem' => $exception->getMessage(),
            ], 503);
        }

        if ($exception instanceof ConnectionException) {
            Log::error('Falha de rede ao falar com a Evolution API (prospecção).', $contexto);

            return response()->json([
                'status' => ProspectWhatsAppService::STATUS_ERROR,
                'mensagem' => 'Não foi possível falar com a Evolution API agora. Tente novamente.',
            ], 502);
        }

        if ($exception instanceof RequestException) {
            Log::error('A Evolution API recusou a requisição (prospecção).', $contexto + [
                'http_status' => $exception->response?->status(),
            ]);

            return response()->json([
                'status' => ProspectWhatsAppService::STATUS_ERROR,
                'mensagem' => 'A Evolution API recusou a solicitação.',
            ], 502);
        }

        Log::error('Erro ao operar o WhatsApp de prospecção.', $contexto + [
            'erro' => $exception::class,
        ]);

        return response()->json([
            'status' => ProspectWhatsAppService::STATUS_ERROR,
            'mensagem' => 'Erro ao operar a instância de prospecção.',
        ], 500);
    }
}
