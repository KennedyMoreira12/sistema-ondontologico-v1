<?php

namespace App\Services\WhatsApp;

use App\Exceptions\EvolutionIndisponivel;
use App\Models\Central\Clinic;
use App\Services\EvolutionService;
use App\Support\EvolutionInstance;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Provisionamento do WhatsApp de UMA clínica.
 *
 * O caminho manual — criar a instância na Evolution, copiar o token, montar o
 * webhook e preencher a URL do n8n — funciona uma vez e falha na segunda, porque
 * cada passo depende de alguém lembrar de todos os outros. Aqui o cadastro da
 * clínica vira a única entrada: nome de instância derivado do próprio registro,
 * token guardado cifrado, webhook apontando para esta aplicação com o segredo e
 * o token da clínica, e destino do n8n resolvido por template.
 *
 * Três regras não são negociáveis e estão implementadas de propósito:
 *
 * 1. Nada de nome fixo. O nome nasce de `clinica-{id}-{slug}`, então duas
 *    clínicas nunca disputam a mesma instância — e `malheiro`/`daiane`, que já
 *    existem, jamais são tocadas por este serviço.
 * 2. Nada de token global. O `apikey` de toda chamada por instância é o token
 *    da própria clínica; a chave administrativa só cria, lista e configura o
 *    webhook.
 * 3. Nada de ativação antes da hora. `Clinic::whatsappPronto()` só é verdadeiro
 *    com instância, token, webhook configurado, conexão aberta e destino de
 *    n8n; o webhook de entrada consulta esse estado antes de entregar mensagem
 *    ao workflow.
 *
 * O provisionamento é best-effort em relação ao cadastro: se a Evolution estiver
 * fora do ar, a clínica continua criada, o erro fica registrado em
 * `whatsapp_last_error` e o painel permite repetir. Derrubar o cadastro inteiro
 * por causa de um terceiro indisponível seria pior.
 */
class ClinicWhatsAppProvisioner
{
    public const STATUS_NAO_CONTRATADO = 'nao_contratado';

    public const STATUS_PROVISIONANDO = 'provisionando';

    public const STATUS_AGUARDANDO_CONEXAO = 'aguardando_conexao';

    public const STATUS_CONECTADO = 'conectado';

    public const STATUS_DESCONECTADO = 'desconectado';

    public const STATUS_ERRO = 'erro';

    /**
     * Eventos que o webhook da instância recebe.
     *
     * É a mesma lista das instâncias que já atendem: mensagens (entrada, status,
     * exclusão, envio), estado da conexão, QR e status da instância. Sem
     * `CONNECTION_UPDATE` o painel não saberia que o número caiu; sem
     * `MESSAGES_UPSERT` o atendimento não existe.
     */
    public const EVENTOS = [
        'MESSAGES_UPSERT',
        'MESSAGES_UPDATE',
        'MESSAGES_DELETE',
        'SEND_MESSAGE',
        'SEND_MESSAGE_UPDATE',
        'CONNECTION_UPDATE',
        'STATUS_INSTANCE',
        'QRCODE_UPDATED',
    ];

    public function __construct(protected EvolutionService $evolution) {}

    /**
     * Garante a instância, o token, o webhook e o roteamento da clínica.
     *
     * Idempotente: pode ser chamado de novo para repetir uma falha ou para
     * reaplicar a configuração. Uma instância que já existe é adotada, nunca
     * renomeada nem recriada.
     *
     * @return array{ok: bool, status: string, criada: bool, instance: ?string, estado: ?string, pendencias: list<string>, erro: ?string}
     */
    public function provision(Clinic $clinic): array
    {
        if (! $clinic->contrataWhatsApp()) {
            $this->marcar($clinic, self::STATUS_NAO_CONTRATADO);

            return [
                'ok' => false,
                'status' => self::STATUS_NAO_CONTRATADO,
                'criada' => false,
                'instance' => $clinic->evolution_instance,
                'estado' => null,
                'pendencias' => ['plano_sem_whatsapp'],
                'erro' => null,
            ];
        }

        $this->marcar($clinic, self::STATUS_PROVISIONANDO);

        try {
            $nome = $this->nomeDaInstancia($clinic);
            $criada = false;
            $info = $this->localizar($nome);

            if ($info === null) {
                $info = $this->normalizar($this->evolution->createInstance($nome));
                $info['name'] = $nome;
                $criada = true;
            }

            /**
             * O token que a Evolution devolve na criação é a única chance de
             * obtê-lo em claro. Se a instância já existia e a listagem não o
             * trouxer, o token que o cadastro já tinha continua valendo; sem
             * nenhum dos dois não há como autenticar e o provisionamento falha
             * em vez de gravar uma clínica meio configurada.
             */
            $token = $info['token'] ?? $clinic->evolution_token;

            if (! is_string($token) || trim($token) === '') {
                throw new RuntimeException('A Evolution não devolveu o token da instância '.$nome.'.');
            }

            $clinic->forceFill([
                'evolution_instance' => $nome,
                'evolution_token' => $token,
                'whatsapp_provisioned_at' => $clinic->whatsapp_provisioned_at ?? now(),
            ])->save();

            $this->configurarWebhook($clinic);
            $this->configurarRoteamento($clinic);
            $this->refresh($clinic);

            $clinic->refresh();

            Log::info('WhatsApp da clínica provisionado.', [
                'clinic_id' => $clinic->id,
                'instance' => $clinic->evolution_instance,
                'criada' => $criada,
                'status' => $clinic->whatsapp_status,
                'pronto' => $clinic->whatsappPronto(),
            ]);

            return [
                'ok' => true,
                'status' => (string) $clinic->whatsapp_status,
                'criada' => $criada,
                'instance' => $clinic->evolution_instance,
                'estado' => $this->ultimoEstado($clinic),
                'pendencias' => $this->pendencias($clinic),
                'erro' => null,
            ];
        } catch (Throwable $exception) {
            $mensagem = $this->mensagemSegura($exception);

            $this->registrarErro($clinic, $mensagem);

            Log::error('Falha ao provisionar o WhatsApp da clínica.', [
                'clinic_id' => $clinic->id,
                'erro' => $exception::class,
                'mensagem' => $mensagem,
            ]);

            return [
                'ok' => false,
                'status' => self::STATUS_ERRO,
                'criada' => false,
                'instance' => $clinic->fresh()?->evolution_instance,
                'estado' => null,
                'pendencias' => $this->pendencias($clinic->fresh()),
                'erro' => $mensagem,
            ];
        }
    }

    /**
     * Consulta o estado real da conexão e grava o resultado.
     *
     * Nunca gera QR nem desconecta: é o que a tela e o monitoramento podem
     * chamar sem medo de derrubar uma clínica conectada.
     *
     * @return array{estado: ?string, conectado: bool, erro: ?string}
     */
    public function refresh(Clinic $clinic): array
    {
        if (! $clinic->hasEvolutionIntegration()) {
            $this->marcar($clinic, self::STATUS_AGUARDANDO_CONEXAO);

            return ['estado' => null, 'conectado' => false, 'erro' => null];
        }

        try {
            $resposta = $this->evolution->connectionState($clinic);
        } catch (Throwable $exception) {
            $mensagem = $this->mensagemSegura($exception);

            $this->registrarErro($clinic, $mensagem);

            return ['estado' => null, 'conectado' => false, 'erro' => $mensagem];
        }

        $estado = $this->estadoDaResposta($resposta);

        $this->registrarConexao($clinic, $estado);

        return ['estado' => $estado, 'conectado' => $estado === 'open', 'erro' => null];
    }

    /**
     * Aplica ao cadastro o estado que a Evolution informou — pelo painel, pelo
     * comando de monitoramento ou pelo próprio webhook `CONNECTION_UPDATE`.
     *
     * `whatsapp_connected_at` só existe enquanto a conexão está aberta: é a
     * prova usada por `whatsappPronto()`, e uma data antiga ali liberaria o
     * atendimento de uma clínica desconectada.
     */
    public function registrarConexao(Clinic $clinic, ?string $estado): void
    {
        $status = match ($estado) {
            'open' => self::STATUS_CONECTADO,
            'connecting' => self::STATUS_AGUARDANDO_CONEXAO,
            'close' => self::STATUS_DESCONECTADO,
            default => $clinic->whatsappProvisionado() ? self::STATUS_ERRO : self::STATUS_AGUARDANDO_CONEXAO,
        };

        $clinic->forceFill([
            'whatsapp_status' => $status,
            'whatsapp_status_at' => now(),
            'whatsapp_connected_at' => $estado === 'open'
                ? ($clinic->whatsapp_connected_at ?? now())
                : null,
        ])->save();
    }

    /**
     * Registra a falha no cadastro, sem derrubar quem chamou.
     *
     * O texto é limitado porque vem de terceiro e a coluna é lida no painel; o
     * corpo cru da Evolution pode carregar token e não pode ser copiado para lá.
     */
    public function registrarErro(Clinic $clinic, string $mensagem): void
    {
        $clinic->forceFill([
            'whatsapp_status' => self::STATUS_ERRO,
            'whatsapp_status_at' => now(),
            'whatsapp_last_error' => mb_substr($mensagem, 0, 1000),
        ])->save();
    }

    /**
     * Limpa o último erro depois de uma entrega bem-sucedida ao n8n.
     *
     * Sem isso o painel continuaria mostrando um erro antigo mesmo com o
     * roteamento funcionando de novo. O status volta a "conectado" quando a
     * conexão está aberta, para uma falha passageira do n8n não deixar a
     * clínica marcada como "erro" até a próxima varredura.
     */
    public function limparErro(Clinic $clinic): void
    {
        if ($clinic->whatsapp_last_error === null) {
            return;
        }

        $clinic->forceFill([
            'whatsapp_last_error' => null,
            'whatsapp_status' => $clinic->whatsapp_connected_at !== null
                ? self::STATUS_CONECTADO
                : $clinic->whatsapp_status,
        ])->save();
    }

    /**
     * O que ainda falta para o atendimento poder ser ativado.
     *
     * @return list<string>
     */
    public function pendencias(?Clinic $clinic): array
    {
        if ($clinic === null) {
            return ['clinica'];
        }

        $pendencias = [];

        if (! $clinic->hasEvolutionIntegration()) {
            $pendencias[] = 'instancia';
        }

        if ($clinic->whatsapp_webhook_configured_at === null) {
            $pendencias[] = 'webhook';
        }

        if (trim((string) $clinic->n8n_webhook_url) === '') {
            $pendencias[] = 'roteamento_n8n';
        }

        if ($clinic->whatsapp_connected_at === null) {
            $pendencias[] = 'conexao';
        }

        return $pendencias;
    }

    /**
     * Nome único da instância, derivado do cadastro.
     *
     * Usa o nome já gravado quando existe — renomear uma instância em uso
     * trocaria o vínculo com o webhook e com o workflow — e, quando não existe,
     * monta `clinica-{id}-{slug}`. O `id` é o que garante unicidade mesmo com
     * duas clínicas de nome idêntico; o laço cobre o caso de o nome já ter sido
     * usado manualmente por outra clínica.
     */
    public function nomeDaInstancia(Clinic $clinic): string
    {
        $atual = trim((string) $clinic->evolution_instance);

        if ($atual !== '') {
            return $atual;
        }

        $base = EvolutionInstance::suggest((int) $clinic->id, (string) $clinic->name);
        $candidato = $base;
        $sufixo = 2;

        while (Clinic::query()
            ->withTrashed()
            ->where('evolution_instance', $candidato)
            ->whereKeyNot($clinic->getKey())
            ->exists()) {
            $marca = '-'.$sufixo++;
            $candidato = rtrim(mb_substr($base, 0, 60 - mb_strlen($marca)), '-._').$marca;
        }

        return $candidato;
    }

    /**
     * Destino do n8n para esta clínica.
     *
     * A coluna da clínica vence sempre: é o ajuste específico de quem tem um
     * workflow próprio. Sem ela, o template da instalação resolve — com
     * `{instance}`, `{clinic_id}` e `{slug}` — e o valor é gravado na coluna,
     * de modo que o destino fica auditável no cadastro. Sem template não há
     * como inventar uma URL: o roteamento fica pendente e o atendimento não é
     * ativado.
     */
    public function resolverDestinoN8n(Clinic $clinic): ?string
    {
        $atual = trim((string) $clinic->n8n_webhook_url);

        if ($atual !== '') {
            return $atual;
        }

        $template = trim((string) config('services.n8n.clinic_webhook_template'));

        if ($template === '') {
            return null;
        }

        return strtr($template, [
            '{instance}' => (string) $clinic->evolution_instance,
            '{clinic_id}' => (string) $clinic->id,
            '{slug}' => (string) Str::slug((string) $clinic->name),
        ]);
    }

    /**
     * URL pública desta aplicação que a Evolution chama.
     *
     * A config existe porque o webhook é montado na Evolution, não no Laravel:
     * atrás de proxy, `route()` pode devolver um host interno que a Evolution
     * não alcança.
     */
    public function urlDoWebhook(): string
    {
        $url = trim((string) config('services.evolution.webhook_url'));

        return $url !== '' ? $url : route('api.evolution.webhook.handle');
    }

    protected function configurarWebhook(Clinic $clinic): void
    {
        $segredo = trim((string) config('services.evolution.webhook_secret'));

        if ($segredo === '') {
            throw new RuntimeException('EVOLUTION_WEBHOOK_SECRET não está configurado; o webhook da clínica ficaria aberto.');
        }

        /**
         * `apikey` e `X-Evolution-Secret` são headers FIXOS que a Evolution
         * repassa em cada entrega. O primeiro é o token desta instância — é o
         * que o `AuthenticateEvolutionWebhook` compara com o da clínica
         * resolvida — e o segundo é o segredo da instalação. Sem os dois, a
         * rota pública recusa a entrega.
         */
        $this->evolution->setWebhook($clinic, [
            'webhook' => [
                'enabled' => true,
                'url' => $this->urlDoWebhook(),
                'headers' => [
                    'apikey' => (string) $clinic->evolution_token,
                    'X-Evolution-Secret' => $segredo,
                ],
                'byEvents' => false,
                'base64' => false,
                'events' => self::EVENTOS,
            ],
        ]);

        $clinic->forceFill(['whatsapp_webhook_configured_at' => now()])->save();
    }

    protected function configurarRoteamento(Clinic $clinic): void
    {
        $destino = $this->resolverDestinoN8n($clinic);

        if ($destino === null || $destino === trim((string) $clinic->n8n_webhook_url)) {
            return;
        }

        $clinic->forceFill(['n8n_webhook_url' => $destino])->save();
    }

    protected function marcar(Clinic $clinic, string $status): void
    {
        $clinic->forceFill([
            'whatsapp_status' => $status,
            'whatsapp_status_at' => now(),
        ])->save();
    }

    /**
     * @return array{name: ?string, uuid: ?string, token: ?string, state: ?string}|null
     */
    protected function localizar(string $nome): ?array
    {
        foreach ($this->evolution->fetchInstances() as $item) {
            $info = $this->normalizar((array) $item);

            if (($info['name'] ?? null) === $nome) {
                return $info;
            }
        }

        return null;
    }

    /**
     * Achata as duas formas que a Evolution usa: item plano
     * (`{name, connectionStatus, hash}`) ou aninhado em `instance`.
     *
     * @param  array<string, mixed>  $item
     * @return array{name: ?string, uuid: ?string, token: ?string, state: ?string}
     */
    protected function normalizar(array $item): array
    {
        $instance = is_array($item['instance'] ?? null) ? $item['instance'] : $item;

        $name = $instance['instanceName'] ?? $instance['name'] ?? null;
        $uuid = $instance['instanceId'] ?? $instance['id'] ?? null;
        $token = $item['hash'] ?? $item['token'] ?? ($instance['token'] ?? null);
        $state = $instance['state'] ?? $instance['connectionStatus'] ?? $instance['status'] ?? null;

        /**
         * A Evolution devolve o token como string em `hash` nas versões novas e
         * como objeto `{apikey: ...}` em algumas respostas de criação. Ler as
         * duas formas evita provisionar uma clínica sem credencial.
         */
        if (is_array($token)) {
            $token = $token['apikey'] ?? $token['token'] ?? null;
        }

        return [
            'name' => is_string($name) ? $name : null,
            'uuid' => is_string($uuid) ? $uuid : (is_scalar($uuid) ? (string) $uuid : null),
            'token' => is_string($token) && $token !== '' ? $token : null,
            'state' => is_string($state) && $state !== '' ? $state : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    protected function estadoDaResposta(array $resposta): ?string
    {
        $estado = $resposta['instance']['state'] ?? $resposta['instance']['status'] ?? null;

        return is_string($estado) && $estado !== '' ? $estado : null;
    }

    protected function ultimoEstado(Clinic $clinic): ?string
    {
        return match ($clinic->whatsapp_status) {
            self::STATUS_CONECTADO => 'open',
            self::STATUS_AGUARDANDO_CONEXAO => 'connecting',
            self::STATUS_DESCONECTADO => 'close',
            default => null,
        };
    }

    /**
     * Traduz a falha sem copiar corpo de erro da Evolution nem segredo para o
     * cadastro. A mensagem vai para o painel e para o log.
     */
    protected function mensagemSegura(Throwable $exception): string
    {
        if ($exception instanceof EvolutionIndisponivel || $exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        if ($exception instanceof ConnectionException) {
            return 'Não foi possível falar com a Evolution API.';
        }

        if ($exception instanceof RequestException) {
            return 'A Evolution API recusou a requisição (HTTP '.($exception->response?->status() ?? '?').').';
        }

        return 'Erro inesperado ao provisionar o WhatsApp ('.class_basename($exception).').';
    }
}
