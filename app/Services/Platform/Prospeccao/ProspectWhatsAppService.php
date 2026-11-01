<?php

namespace App\Services\Platform\Prospeccao;

use App\Exceptions\EvolutionIndisponivel;
use App\Models\Central\ProspectWhatsAppSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente da Evolution API para a instância EXCLUSIVA de prospecção.
 *
 * Não reaproveita o `EvolutionService` das clínicas de propósito: aquele recebe
 * uma `Clinic` e usa o token da clínica; este só conhece o registro de
 * `prospect_whatsapp_settings` e o nome fixo da config, então uma chamada de
 * prospecção nunca alcança a instância de uma clínica. A instância `malheiro`
 * (e qualquer outra que não seja a de prospecção) está fora do alcance do
 * código: nenhum método daqui aceita um nome de instância vindo de fora.
 *
 * A chave administrativa (global) só é usada para LISTAR e CRIAR instâncias.
 * Estado e QR usam o token da própria instância, que a Evolution devolve no
 * `create` e que fica cifrado no banco central.
 */
class ProspectWhatsAppService
{
    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_AWAITING_QR = 'awaiting_qr';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_ERROR = 'error';

    /**
     * Nome fixo da instância de prospecção. Vem da config e nunca do navegador.
     */
    public function instanceName(): string
    {
        return (string) config('prospeccao.whatsapp.default_instance');
    }

    public function setting(): ?ProspectWhatsAppSetting
    {
        return ProspectWhatsAppSetting::current();
    }

    /**
     * Estado apenas do que está gravado no banco, sem tocar a Evolution: é o que
     * a página usa ao abrir, para não consultar (nem parear) nada sozinha.
     *
     * @return array<string, mixed>
     */
    public function storedSummary(?ProspectWhatsAppSetting $setting): array
    {
        if ($setting === null) {
            return [
                'configurado' => false,
                'conectado' => false,
                'status' => self::STATUS_NOT_CONFIGURED,
                'status_label' => $this->statusLabel(self::STATUS_NOT_CONFIGURED),
                'instance' => $this->instanceName(),
            ];
        }

        return [
            'configurado' => true,
            'conectado' => $setting->status === self::STATUS_CONNECTED,
            'status' => $setting->status,
            'status_label' => $this->statusLabel($setting->status),
            'instance' => $setting->instance_name,
            'last_state' => $setting->last_state,
            'last_state_at' => $setting->last_state_at?->toIso8601String(),
            'last_error' => $setting->last_error,
        ];
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_CONNECTED => 'Conectada',
            self::STATUS_AWAITING_QR => 'Aguardando QR',
            self::STATUS_DISCONNECTED => 'Desconectada',
            self::STATUS_ERROR => 'Erro',
            default => 'Não configurada',
        };
    }

    /**
     * Garante que a instância de prospecção existe. Idempotente: se já houver
     * registro local, não faz nada; se a instância já existir na Evolution com o
     * nome fixo, apenas adota (sem recriar nem sobrescrever). Nunca envia número:
     * o pareamento é por QR.
     *
     * @return array{created: bool, setting: ProspectWhatsAppSetting}
     */
    public function ensureInstance(): array
    {
        $existing = $this->setting();

        if ($existing !== null) {
            return ['created' => false, 'setting' => $existing];
        }

        $name = $this->instanceName();
        $info = $this->locate($name);

        if ($info === null) {
            $resposta = $this->createInstance($name);
            $info = $this->normalizeInstance($resposta);
            $info['name'] = $name;
        }

        $state = is_string($info['state'] ?? null) ? $info['state'] : null;
        $status = $state === null ? self::STATUS_DISCONNECTED : $this->mapState($state);

        $setting = ProspectWhatsAppSetting::query()->create([
            'instance_name' => $name,
            'instance_uuid' => $info['uuid'] ?? null,
            'instance_token' => $info['token'] ?? null,
            'status' => $status,
            'last_state' => $state,
            'last_state_at' => now(),
            'connected_at' => $status === self::STATUS_CONNECTED ? now() : null,
        ]);

        return ['created' => true, 'setting' => $setting];
    }

    /**
     * Consulta o estado real na Evolution e atualiza o registro. Não gera QR.
     *
     * @return array<string, mixed> resposta crua (para extrair estado)
     */
    public function refreshState(ProspectWhatsAppSetting $setting): array
    {
        $response = $this->request($setting->instance_token)
            ->get($this->url('/instance/connectionState/'.$setting->instance_name));

        $response->throw();

        $data = $this->json($response);
        $state = $data['instance']['state'] ?? $data['instance']['status'] ?? null;
        $state = is_string($state) && $state !== '' ? $state : null;

        $this->persistState($setting, $state);

        return $data;
    }

    /**
     * Inicia/retoma o pareamento e devolve o QR. Só é chamado por ação explícita
     * do super admin. Se a instância já estiver conectada, devolve o estado sem
     * gerar QR e sem desconectar.
     *
     * @return array{conectado: bool, status: string, qrcode?: ?string}
     */
    public function qrcode(ProspectWhatsAppSetting $setting): array
    {
        $this->refreshState($setting);
        $setting->refresh();

        if ($setting->status === self::STATUS_CONNECTED) {
            return ['conectado' => true, 'status' => self::STATUS_CONNECTED, 'qrcode' => null];
        }

        $response = $this->request($setting->instance_token)
            ->get($this->url('/instance/connect/'.$setting->instance_name));

        $response->throw();

        $data = $this->json($response);
        $base64 = $data['base64'] ?? ($data['qrcode']['base64'] ?? null);
        $base64 = is_string($base64) && $base64 !== '' ? $base64 : null;

        if ($base64 !== null) {
            $setting->fill([
                'status' => self::STATUS_AWAITING_QR,
                'last_state' => 'connecting',
                'last_state_at' => now(),
                'last_error' => null,
            ])->save();

            return ['conectado' => false, 'status' => self::STATUS_AWAITING_QR, 'qrcode' => $base64];
        }

        $state = $data['instance']['state'] ?? $data['instance']['status'] ?? null;
        $state = is_string($state) && $state !== '' ? $state : null;

        if ($this->mapState($state) === self::STATUS_CONNECTED) {
            $this->persistState($setting, $state);

            return ['conectado' => true, 'status' => self::STATUS_CONNECTED, 'qrcode' => null];
        }

        throw new RuntimeException('A Evolution não devolveu um QR Code para a instância de prospecção.');
    }

    /**
     * Instâncias conhecidas pela Evolution. Requer a chave administrativa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchInstances(): array
    {
        $response = $this->request(null, true)
            ->get($this->url('/instance/fetchInstances'));

        $response->throw();

        $data = $this->json($response);

        return array_is_list($data) ? $data : [];
    }

    /**
     * Cria a instância na Evolution. Usa a chave administrativa e envia
     * `qrcode: false` de propósito: o QR é gerado só quando o operador pedir.
     *
     * @return array<string, mixed>
     */
    public function createInstance(string $name): array
    {
        $response = $this->request(null, true)
            ->post($this->url('/instance/create'), [
                'instanceName' => $name,
                'integration' => 'WHATSAPP-BAILEYS',
                'qrcode' => false,
            ]);

        $response->throw();

        return $this->json($response);
    }

    /**
     * Envia texto pela instância EXCLUSIVA de prospecção.
     *
     * Usa o token da própria instância (não a chave administrativa): a chave
     * global só lista/cria instâncias. O nome da instância vem do registro em
     * `prospect_whatsapp_settings`, não de um valor fixo, para não divergir se a
     * instância for renomeada.
     *
     * Erros previstos (autenticação, instância ausente, número sem WhatsApp,
     * Evolution fora do ar) NÃO lançam: devolvem categoria + motivo para a
     * camada HTTP responder com clareza e registrar o resultado correto.
     *
     * @param  string  $number  Só dígitos, com DDI.
     * @return array{ok: bool, http: ?int, categoria: string, motivo: string, dados: array<string, mixed>, sem_whatsapp: bool}
     */
    public function sendText(string $number, string $text): array
    {
        $setting = $this->setting();

        if ($setting === null) {
            return $this->resultadoEnvio(false, null, 'nao_configurada', 'A instância de prospecção ainda não foi configurada.');
        }

        try {
            $response = $this->request($setting->instance_token)
                ->post($this->url('/message/sendText/'.$setting->instance_name), [
                    'number' => $this->onlyDigits($number),
                    'text' => $text,
                ]);
        } catch (\Throwable $e) {
            return $this->resultadoEnvio(false, null, 'conexao', 'Não foi possível conectar à Evolution API. Verifique se o serviço está no ar.');
        }

        $http = $response->status();
        $dados = $this->json($response);

        if ($response->successful() && ! isset($dados['error'])) {
            return $this->resultadoEnvio(true, $http, 'enviado', '', $dados);
        }

        return $this->classificarErroDeEnvio($http, $dados);
    }

    /**
     * Traduz a recusa da Evolution para uma categoria estável e uma mensagem
     * compreensível. O corpo cru vai para o log pela camada que chama.
     *
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, http: ?int, categoria: string, motivo: string, dados: array<string, mixed>, sem_whatsapp: bool}
     */
    protected function classificarErroDeEnvio(int $http, array $dados): array
    {
        $semWhatsApp = $this->respostaIndicaNumeroSemWhatsApp($dados);

        $categoria = match (true) {
            $http === 401, $http === 403 => 'autenticacao',
            $http === 404 => 'instancia',
            $semWhatsApp => 'sem_whatsapp',
            $http === 400, $http === 422 => 'requisicao',
            $http >= 500, $http === 200 => 'evolution',
            default => 'desconhecido',
        };

        $motivo = match ($categoria) {
            'autenticacao' => 'A Evolution recusou a autenticação da instância de prospecção. Verifique o token da instância no painel.',
            'instancia' => 'A instância de prospecção não existe mais na Evolution. Reconecte a instância no painel.',
            'sem_whatsapp' => 'O número informado não possui conta no WhatsApp.',
            'requisicao' => 'A Evolution recusou a requisição de envio (dados inválidos).',
            'evolution' => 'A Evolution apresentou um erro interno ao enviar a mensagem.',
            default => 'Não foi possível enviar a mensagem pela Evolution API.',
        };

        return $this->resultadoEnvio(false, $http, $categoria, $motivo, $dados, $semWhatsApp);
    }

    /**
     * A Evolution devolve, no 400 de número inexistente, o mesmo formato do
     * `chat/whatsappNumbers`: uma lista de `{jid, exists, number}`.
     *
     * @param  array<string, mixed>  $dados
     */
    protected function respostaIndicaNumeroSemWhatsApp(array $dados): bool
    {
        $linhas = $dados['response']['message'] ?? [];

        if (! is_array($linhas)) {
            return false;
        }

        foreach ($linhas as $linha) {
            if (is_array($linha) && array_key_exists('exists', $linha) && $linha['exists'] === false) {
                return true;
            }
        }

        return false;
    }

    protected function onlyDigits(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, http: ?int, categoria: string, motivo: string, dados: array<string, mixed>, sem_whatsapp: bool}
     */
    protected function resultadoEnvio(bool $ok, ?int $http, string $categoria, string $motivo, array $dados = [], bool $semWhatsApp = false): array
    {
        return [
            'ok' => $ok,
            'http' => $http,
            'categoria' => $categoria,
            'motivo' => $motivo,
            'dados' => $dados,
            'sem_whatsapp' => $semWhatsApp,
        ];
    }

    /**
     * Localiza uma instância pelo nome exato dentro das respostas da Evolution.
     *
     * @return array{name: ?string, uuid: ?string, token: ?string, state: ?string}|null
     */
    protected function locate(string $name): ?array
    {
        foreach ($this->fetchInstances() as $item) {
            $info = $this->normalizeInstance((array) $item);

            if (($info['name'] ?? null) === $name) {
                return $info;
            }
        }

        return null;
    }

    /**
     * Achata as duas formas que a Evolution usa: item plano
     * (`{name, connectionStatus, token}`) ou aninhado em `instance`.
     *
     * @param  array<string, mixed>  $item
     * @return array{name: ?string, uuid: ?string, token: ?string, state: ?string}
     */
    protected function normalizeInstance(array $item): array
    {
        $instance = is_array($item['instance'] ?? null) ? $item['instance'] : $item;

        $name = $instance['instanceName'] ?? $instance['name'] ?? null;
        $uuid = $instance['instanceId'] ?? $instance['id'] ?? null;
        $token = $item['hash'] ?? $item['token'] ?? ($instance['token'] ?? null);
        $state = $instance['state'] ?? $instance['connectionStatus'] ?? $instance['status'] ?? null;

        return [
            'name' => is_string($name) ? $name : null,
            'uuid' => is_string($uuid) ? $uuid : (is_scalar($uuid) ? (string) $uuid : null),
            'token' => is_string($token) && $token !== '' ? $token : null,
            'state' => is_string($state) && $state !== '' ? $state : null,
        ];
    }

    protected function persistState(ProspectWhatsAppSetting $setting, ?string $state): void
    {
        $status = $this->mapState($state);

        $setting->fill([
            'status' => $status,
            'last_state' => $state,
            'last_state_at' => now(),
            'connected_at' => $status === self::STATUS_CONNECTED
                ? ($setting->connected_at ?? now())
                : $setting->connected_at,
            'last_error' => null,
        ])->save();
    }

    public function mapState(?string $state): string
    {
        return match ($state) {
            'open' => self::STATUS_CONNECTED,
            'connecting' => self::STATUS_AWAITING_QR,
            'close' => self::STATUS_DISCONNECTED,
            default => self::STATUS_ERROR,
        };
    }

    /**
     * Base da requisição. Com `true`, usa a chave administrativa (listar/criar);
     * sem argumento, usa o token da instância de prospecção.
     */
    protected function request(?string $instanceToken = null, bool $admin = false): PendingRequest
    {
        $this->assertConfigured();

        $apikey = $admin
            ? (string) config('services.evolution.admin_key')
            : ($instanceToken ?: (string) config('services.evolution.admin_key'));

        return Http::timeout((int) config('services.evolution.timeout', 30))
            ->acceptJson()
            ->withHeaders(['apikey' => $apikey]);
    }

    protected function assertConfigured(): void
    {
        if (rtrim((string) config('services.evolution.url'), '/') === '') {
            throw EvolutionIndisponivel::semUrl();
        }

        if ((string) config('services.evolution.admin_key') === '') {
            throw EvolutionIndisponivel::semChaveAdministrativa();
        }
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('services.evolution.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        $data = $response->json() ?? [];

        return is_array($data) ? $data : [];
    }
}
