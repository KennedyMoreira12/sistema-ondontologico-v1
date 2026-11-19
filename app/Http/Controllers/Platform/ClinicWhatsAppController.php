<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\EvolutionIndisponivel;
use App\Http\Controllers\Controller;
use App\Models\Central\Clinic;
use App\Services\EvolutionService;
use App\Services\WhatsApp\ClinicWhatsAppProvisioner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Conexão do WhatsApp de uma clínica pelo painel da plataforma.
 *
 * A clínica vem sempre do route model binding (`{clinic}`) e a autorização do
 * contexto autenticado — nunca de um `clinic_id` no corpo. A instância e o token
 * são lidos do próprio registro da clínica, então uma requisição para a clínica
 * A jamais alcança a instância da clínica B, e nenhum segredo vai ao navegador.
 *
 * Nada aqui cria, apaga, reinicia ou desconecta instância: quando já está
 * conectada, a tela apenas mostra o estado, e o QR é pedido só no clique.
 */
class ClinicWhatsAppController extends Controller
{
    public function __construct(
        protected EvolutionService $evolution,
        protected ClinicWhatsAppProvisioner $provisioner,
    ) {}

    /**
     * Estado atual da conexão. Somente leitura: não dispara pareamento nem gera
     * QR, para a tela abrir sem mexer numa instância conectada.
     */
    public function show(Clinic $clinic): JsonResponse
    {
        if (! $clinic->hasEvolutionIntegration()) {
            return response()->json([
                'configurado' => false,
                'estado' => null,
                'numero' => $clinic->whatsapp,
                'mensagem' => 'Esta clínica ainda não tem instância da Evolution configurada. Provisione a instância pelo procedimento administrativo antes de conectar o WhatsApp.',
            ]);
        }

        try {
            $estado = $this->estadoDaResposta($this->evolution->connectionState($clinic));
        } catch (Throwable $exception) {
            return $this->respostaDeFalha($clinic, $exception);
        }

        /**
         * O estado observado é gravado: é a mesma informação que o webhook
         * `CONNECTION_UPDATE` e o comando de monitoramento persistem, e deixar
         * o banco desatualizado faria `whatsappPronto()` liberar atendimento de
         * uma instância que a tela acabou de ver fechada.
         */
        $this->provisioner->registrarConexao($clinic, $estado);

        return response()->json([
            'configurado' => true,
            'estado' => $estado,
            'conectado' => $estado === 'open',
            'numero' => $clinic->whatsapp,
            'provisionado' => $clinic->whatsappProvisionado(),
            'pronto' => $clinic->whatsappPronto(),
            'status' => $clinic->whatsapp_status,
            'pendencias' => $this->provisioner->pendencias($clinic),
            'webhook_configurado' => $clinic->whatsapp_webhook_configured_at !== null,
            'roteamento' => trim((string) $clinic->n8n_webhook_url) !== '',
            'ultimo_erro' => $clinic->whatsapp_last_error,
        ]);
    }

    /**
     * Gera (ou recupera) o QR Code do WhatsApp da clínica.
     *
     * O número informado é validado e guardado no cadastro da clínica; ele NÃO é
     * enviado à Evolution porque isso trocaria o QR por um código de pareamento
     * por telefone. Se a instância já estiver conectada, devolve o estado sem
     * desconectar e sem gerar outro QR.
     */
    public function qrcode(Request $request, Clinic $clinic): JsonResponse
    {
        $this->authorizePlatformAction();

        $validated = $request->validate([
            'numero' => ['nullable', 'string', 'regex:/^\d{10,15}$/'],
        ], [
            'numero.regex' => 'Informe o número com DDI e DDD, apenas dígitos.',
        ]);

        if (! $clinic->hasEvolutionIntegration()) {
            return response()->json([
                'estado' => null,
                'mensagem' => 'Esta clínica ainda não tem instância da Evolution configurada. Provisione a instância pelo procedimento administrativo antes de conectar o WhatsApp.',
            ], 409);
        }

        if (($validated['numero'] ?? null) !== null) {
            $clinic->update(['whatsapp' => $validated['numero']]);
        }

        try {
            if ($this->estadoDaResposta($this->evolution->connectionState($clinic)) === 'open') {
                $this->provisioner->registrarConexao($clinic, 'open');

                return response()->json([
                    'estado' => 'open',
                    'conectado' => true,
                    'numero' => $clinic->fresh()->whatsapp,
                    'mensagem' => 'O WhatsApp desta clínica já está conectado.',
                ]);
            }

            $resposta = $this->evolution->connect($clinic);
        } catch (Throwable $exception) {
            return $this->respostaDeFalha($clinic, $exception);
        }

        if (($resposta['error'] ?? false) === true) {
            Log::warning('A Evolution API recusou a geração do QR Code.', [
                'clinic_id' => $clinic->id,
            ]);

            $this->provisioner->registrarErro($clinic, 'A Evolution API recusou a geração do QR Code.');

            return response()->json([
                'estado' => null,
                'mensagem' => 'A Evolution API recusou a solicitação. Tente novamente em instantes.',
            ], 502);
        }

        $base64 = $this->qrBase64($resposta);

        if ($base64 !== null) {
            $this->provisioner->registrarConexao($clinic, 'connecting');

            return response()->json([
                'estado' => 'connecting',
                'conectado' => false,
                'qrcode' => $base64,
                'numero' => $clinic->fresh()->whatsapp,
            ]);
        }

        if ($this->estadoDaResposta($resposta) === 'open') {
            $this->provisioner->registrarConexao($clinic, 'open');

            return response()->json([
                'estado' => 'open',
                'conectado' => true,
                'numero' => $clinic->fresh()->whatsapp,
                'mensagem' => 'O WhatsApp desta clínica já está conectado.',
            ]);
        }

        $this->provisioner->registrarErro($clinic, 'A Evolution não devolveu um QR Code para a instância.');

        return response()->json([
            'estado' => null,
            'mensagem' => 'A Evolution não devolveu um QR Code. A instância pode precisar ser provisionada pelo procedimento administrativo.',
        ], 409);
    }

    /**
     * Cria (ou reaplica) a instância, o token, o webhook e o roteamento n8n.
     *
     * Existe para dois casos: a clínica que foi cadastrada com a Evolution fora
     * do ar — o provisionamento automático falhou e o operador repete sem
     * recadastrar — e a que precisa reaplicar a configuração depois de uma
     * mudança de domínio ou de segredo. É idempotente e nunca renomeia uma
     * instância existente.
     */
    public function provisionar(Clinic $clinic): JsonResponse
    {
        $this->authorizePlatformAction();

        $resultado = $this->provisioner->provision($clinic);
        $clinic->refresh();

        $http = match (true) {
            $resultado['ok'] => 200,
            in_array('plano_sem_whatsapp', $resultado['pendencias'], true) => 409,
            default => 502,
        };

        return response()->json([
            'ok' => $resultado['ok'],
            'status' => $clinic->whatsapp_status,
            'estado' => $resultado['estado'],
            'conectado' => $clinic->whatsapp_connected_at !== null,
            'provisionado' => $clinic->whatsappProvisionado(),
            'pronto' => $clinic->whatsappPronto(),
            'pendencias' => $resultado['pendencias'],
            'numero' => $clinic->whatsapp,
            'mensagem' => $resultado['ok']
                ? 'Instância provisionada. Gere o QR Code para conectar o número.'
                : ($resultado['erro'] ?? 'O plano desta clínica não inclui o WhatsApp.'),
        ], $http);
    }

    /**
     * O estado da instância dentro de uma resposta da Evolution.
     *
     * `connectionState` devolve `instance.state`; `connect` num estado
     * desconhecido devolve `instance.status`. Os dois entram.
     *
     * @param  array<string, mixed>  $resposta
     */
    protected function estadoDaResposta(array $resposta): ?string
    {
        $estado = $resposta['instance']['state'] ?? $resposta['instance']['status'] ?? null;

        return is_string($estado) && $estado !== '' ? $estado : null;
    }

    /**
     * O QR como data URI, venha ele plano (`connect`) ou aninhado (`qrcode`).
     *
     * @param  array<string, mixed>  $resposta
     */
    protected function qrBase64(array $resposta): ?string
    {
        $base64 = $resposta['base64'] ?? ($resposta['qrcode']['base64'] ?? null);

        return is_string($base64) && $base64 !== '' ? $base64 : null;
    }

    /**
     * Traduz a falha sem devolver corpo de erro da Evolution nem segredo algum.
     *
     * A mensagem traduzida também é gravada em `whatsapp_last_error`: o painel
     * precisa dizer "a Evolution recusou" na próxima vez que abrir, mesmo que
     * ninguém esteja olhando o log na hora.
     */
    protected function respostaDeFalha(Clinic $clinic, Throwable $exception): JsonResponse
    {
        [$mensagem, $http] = $this->traduzirFalha($clinic, $exception);

        $this->provisioner->registrarErro($clinic, $mensagem);

        return response()->json(['estado' => null, 'mensagem' => $mensagem], $http);
    }

    /**
     * @return array{0: string, 1: int}
     */
    protected function traduzirFalha(Clinic $clinic, Throwable $exception): array
    {
        if ($exception instanceof EvolutionIndisponivel) {
            return [$exception->getMessage(), 503];
        }

        if ($exception instanceof ConnectionException) {
            Log::error('Falha de rede ao falar com a Evolution API pelo painel.', ['clinic_id' => $clinic->id]);

            return ['Não foi possível falar com a Evolution API agora. Tente novamente.', 502];
        }

        if ($exception instanceof RequestException) {
            Log::error('A Evolution API recusou a requisição do painel.', [
                'clinic_id' => $clinic->id,
                'status' => $exception->response?->status(),
            ]);

            return ['A Evolution API recusou a solicitação.', 502];
        }

        Log::error('Erro inesperado ao consultar a Evolution API pelo painel.', [
            'clinic_id' => $clinic->id,
            'erro' => $exception::class,
        ]);

        return ['Erro ao consultar a Evolution API.', 500];
    }

    protected function authorizePlatformAction(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() === true, 403, 'Apenas o super admin pode conectar o WhatsApp.');
    }
}
