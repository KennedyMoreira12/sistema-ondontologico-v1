<?php

namespace App\Services\WhatsApp;

use App\Exceptions\EvolutionIndisponivel;
use App\Jobs\SendWhatsAppOutboxMessage;
use App\Models\Central\Clinic;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppOutboxMessage;
use App\Services\EvolutionService;
use App\Services\Tenant\TenantManager;
use App\Support\EvolutionPayload;
use App\Support\WhatsAppDeliveryStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * A outbox transacional do WhatsApp.
 *
 * Separa DECIDIR enviar de ENVIAR. O registro nasce antes da chamada HTTP, o
 * worker reivindica a linha com compare-and-swap (duas execuções nunca enviam a
 * mesma linha) e a recuperação encontra o que ficou pendente depois de um
 * restart.
 *
 * A chave de idempotência é `message_key`: um reenvio do n8n com a mesma
 * `idempotency_key` reencontra a linha em vez de criar outra. Sem chave
 * explícita, cada pedido é uma mensagem nova — comportamento igual ao de antes,
 * porque dois "Olá" iguais podem ser legítimos.
 *
 * Sobre retry: repetir depois de um timeout pode DUPLICAR a mensagem no
 * WhatsApp, porque não há como saber se a Evolution recebeu antes de morrer. Por
 * isso a classificação é conservadora:
 *
 * - 5xx/429: a Evolution respondeu que não aceitou. Retry seguro.
 * - 4xx, `error` no corpo, integração ausente: não melhora sozinho. DLQ.
 * - timeout de conexão: entrega INCERTA. DLQ para revisão humana, nunca retry
 *   automático.
 * - recusa de conexão/DNS: não saiu. Retry seguro.
 */
class Outbox
{
    public function __construct(
        protected TenantManager $tenants,
        protected EvolutionService $evolution,
        protected MenuInterativo $menus,
    ) {}

    protected const FALHA_RETRY = 'retry';

    protected const FALHA_DEFINITIVA = 'dead';

    protected const FALHA_INCERTA = 'uncertain';

    /**
     * Cria (ou reencontra, quando a chave de idempotência repete) a linha.
     *
     * @return WhatsAppOutboxMessage A linha criada ou a existente; consulte
     *                               `wasRecentlyCreated` para saber se é nova.
     */
    public function enqueue(
        Clinic $clinic,
        string $telefone,
        string $texto,
        ?string $idempotencyKey = null,
        ?string $correlationId = null,
        string $source = 'n8n',
    ): WhatsAppOutboxMessage {
        $numero = $this->normalizarTelefone($telefone);
        $jid = $numero.'@s.whatsapp.net';

        $chave = $this->chaveDeIdempotencia($clinic, $numero, $texto, $idempotencyKey);

        return $this->criarRegistro([
            'message_key' => $chave,
            'remote_jid' => $jid,
            'telefone' => $numero,
            'kind' => 'text',
            'body' => $texto,
            'source' => $source,
            'status' => WhatsAppOutboxMessage::PENDING,
            'attempts' => 0,
            'max_attempts' => max(1, (int) config('whatsapp.outbox.max_attempts', 5)),
            'available_at' => now(),
            'correlation_id' => $correlationId,
        ]);
    }

    /**
     * Enfileira um menu de botões/lista.
     *
     * `body` guarda o texto numerado de fallback, e `payload` guarda o contrato
     * interno do menu. A separação é o que permite o job reenviar como TEXTO
     * quando a Evolution recusar o formato interativo, sem perder a mensagem e
     * sem duplicá-la: a recusa é explícita (4xx ou `error` no corpo), então
     * reenviar em outro formato é seguro.
     *
     * @param  array<string, mixed>  $menu
     */
    public function enqueueInterativo(
        Clinic $clinic,
        string $telefone,
        array $menu,
        string $textoFallback,
        ?string $idempotencyKey = null,
        ?string $correlationId = null,
        string $source = 'n8n',
    ): WhatsAppOutboxMessage {
        $numero = $this->normalizarTelefone($telefone);
        $jid = $numero.'@s.whatsapp.net';

        $conteudo = json_encode($menu, JSON_UNESCAPED_UNICODE) ?: $textoFallback;
        $chave = $this->chaveDeIdempotencia($clinic, $numero, $conteudo, $idempotencyKey);

        return $this->criarRegistro([
            'message_key' => $chave,
            'remote_jid' => $jid,
            'telefone' => $numero,
            'kind' => (string) ($menu['tipo'] ?? 'text'),
            'body' => $textoFallback,
            'payload' => $menu,
            'source' => $source,
            'status' => WhatsAppOutboxMessage::PENDING,
            'attempts' => 0,
            'max_attempts' => max(1, (int) config('whatsapp.outbox.max_attempts', 5)),
            'available_at' => now(),
            'correlation_id' => $correlationId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function criarRegistro(array $atributos): WhatsAppOutboxMessage
    {
        $chave = (string) $atributos['message_key'];

        try {
            return WhatsAppOutboxMessage::create($atributos);
        } catch (UniqueConstraintViolationException) {
            $existente = WhatsAppOutboxMessage::query()
                ->where('message_key', $chave)
                ->first();

            if ($existente === null) {
                throw new \RuntimeException(
                    "A outbox não conseguiu recuperar a linha [{$chave}] depois de uma violação de unicidade."
                );
            }

            return $existente;
        }
    }

    /**
     * Entrega a linha à fila.
     *
     * O job carrega apenas ids. A clínica é resolvida de novo no worker porque
     * a conexão `tenant` não atravessa o processo da fila.
     */
    public function dispatch(Clinic $clinic, WhatsAppOutboxMessage $registro): void
    {
        $job = new SendWhatsAppOutboxMessage((int) $clinic->id, (int) $registro->id);

        $connection = config('whatsapp.outbox.connection');
        $queue = (string) config('whatsapp.outbox.queue', 'default');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        dispatch($job->onQueue($queue === '' ? 'default' : $queue));
    }

    /**
     * Reivindica e processa uma linha.
     *
     * A reivindicação é um UPDATE condicional: só uma execução consegue mudar
     * `pending` para `processing`. A perdedora sai em silêncio, porque a
     * vencedora está fazendo exatamente o mesmo trabalho.
     */
    public function process(int $outboxId): ?WhatsAppOutboxMessage
    {
        $agora = now();

        $reivindicada = WhatsAppOutboxMessage::query()
            ->whereKey($outboxId)
            ->where('status', WhatsAppOutboxMessage::PENDING)
            ->where(function ($query) use ($agora): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', $agora);
            })
            ->update([
                'status' => WhatsAppOutboxMessage::PROCESSING,
                'locked_at' => $agora,
                'attempts' => DB::raw('attempts + 1'),
            ]);

        if ($reivindicada !== 1) {
            return null;
        }

        $registro = WhatsAppOutboxMessage::query()->find($outboxId);

        if ($registro === null) {
            return null;
        }

        $clinica = $this->tenants->require();

        try {
            $resposta = $this->enviarRegistro($clinica, $registro);
        } catch (EvolutionIndisponivel $exception) {
            return $this->falhar($registro, $exception->getMessage(), 'sem_integracao', self::FALHA_DEFINITIVA);
        } catch (RequestException $exception) {
            $status = (int) ($exception->response?->status() ?? 0);
            $mensagem = "A Evolution recusou o envio (HTTP {$status}): ".$this->resumir($exception->getMessage());

            // 429 e 5xx são do lado de lá e melhoram com espera. 4xx é
            // configuração/credencial: repetir não muda nada.
            $tipo = ($status === 429 || $status >= 500)
                ? self::FALHA_RETRY
                : self::FALHA_DEFINITIVA;

            if ($tipo === self::FALHA_DEFINITIVA && $this->ehInterativo($registro)) {
                return $this->enviarFallbackTextual($clinica, $registro, $mensagem);
            }

            return $this->falhar($registro, $mensagem, 'evolution_recusou', $tipo);
        } catch (ConnectionException $exception) {
            $mensagem = 'Falha de conexão com a Evolution: '.$this->resumir($exception->getMessage());

            // Timeout é ambíguo: a requisição pode ter chegado e a resposta
            // ter se perdido. Reenviar duplicaria a mensagem no celular do
            // paciente — o pior resultado possível.
            if ($this->pareceTimeout($exception)) {
                return $this->falhar($registro, $mensagem, 'incerto', self::FALHA_INCERTA);
            }

            return $this->falhar($registro, $mensagem, 'conexao', self::FALHA_RETRY);
        } catch (Throwable $exception) {
            Log::error('Erro inesperado ao enviar mensagem da outbox.', [
                'outbox_id' => $registro->id,
                'message_key' => $registro->message_key,
                'exception' => $exception::class,
            ]);

            return $this->falhar($registro, 'Erro inesperado: '.$this->resumir($exception->getMessage()), 'exception', self::FALHA_RETRY);
        }

        if (isset($resposta['error'])) {
            $mensagem = 'A Evolution respondeu com erro no corpo: '.$this->resumir((string) json_encode($resposta['error']));

            if ($this->ehInterativo($registro)) {
                return $this->enviarFallbackTextual($clinica, $registro, $mensagem);
            }

            return $this->falhar($registro, $mensagem, 'evolution_recusou', self::FALHA_DEFINITIVA);
        }

        return $this->concluir($registro, $resposta);
    }

    /**
     * Envia a linha conforme o tipo: texto, botões ou lista.
     *
     * Linha interativa sem payload é tratada como texto: é o estado possível de
     * uma linha antiga ou de um payload removido à mão, e mandar o texto
     * numerado é melhor do que estourar no worker.
     *
     * @return array<string, mixed>
     */
    protected function enviarRegistro(Clinic $clinica, WhatsAppOutboxMessage $registro): array
    {
        $payload = is_array($registro->payload) ? $registro->payload : [];

        if ($this->ehInterativo($registro) && $payload !== []) {
            return $this->menus->enviar(
                $this->evolution,
                $clinica,
                (string) $registro->telefone,
                $payload,
            );
        }

        return $this->evolution->sendText(
            $clinica,
            (string) $registro->telefone,
            (string) $registro->body,
        );
    }

    protected function ehInterativo(WhatsAppOutboxMessage $registro): bool
    {
        return in_array((string) $registro->kind, [MenuInterativo::TIPO_BOTOES, MenuInterativo::TIPO_LISTA], true);
    }

    /**
     * Reenvia como texto numerado um menu que a Evolution recusou.
     *
     * Só é chamado depois de uma recusa EXPLÍCITA (4xx ou `error` no corpo).
     * Timeout continua indo para a DLQ sem fallback, porque nesse caso o menu
     * pode ter sido entregue — mandar o texto depois duplicaria a mensagem no
     * celular do paciente.
     */
    protected function enviarFallbackTextual(
        Clinic $clinica,
        WhatsAppOutboxMessage $registro,
        string $motivo,
    ): WhatsAppOutboxMessage {
        // A linha vira texto ANTES da tentativa: se o envio de fallback falhar
        // de forma transitória, o retry já sai no formato certo em vez de
        // tentar o menu recusado de novo.
        $registro->forceFill(['kind' => 'text', 'payload' => null])->save();

        try {
            $resposta = $this->evolution->sendText(
                $clinica,
                (string) $registro->telefone,
                (string) $registro->body,
            );
        } catch (EvolutionIndisponivel $exception) {
            return $this->falhar($registro, $exception->getMessage(), 'sem_integracao', self::FALHA_DEFINITIVA);
        } catch (RequestException $exception) {
            $status = (int) ($exception->response?->status() ?? 0);

            return $this->falhar(
                $registro,
                "O fallback textual também foi recusado (HTTP {$status}): ".$this->resumir($exception->getMessage()),
                'evolution_recusou',
                ($status === 429 || $status >= 500) ? self::FALHA_RETRY : self::FALHA_DEFINITIVA,
            );
        } catch (ConnectionException $exception) {
            $mensagem = 'Falha de conexão no fallback textual: '.$this->resumir($exception->getMessage());

            return $this->falhar(
                $registro,
                $mensagem,
                $this->pareceTimeout($exception) ? 'incerto' : 'conexao',
                $this->pareceTimeout($exception) ? self::FALHA_INCERTA : self::FALHA_RETRY,
            );
        } catch (Throwable $exception) {
            return $this->falhar($registro, 'Erro inesperado: '.$this->resumir($exception->getMessage()), 'exception', self::FALHA_RETRY);
        }

        if (isset($resposta['error'])) {
            return $this->falhar(
                $registro,
                'O fallback textual também voltou com erro: '.$this->resumir((string) json_encode($resposta['error'])),
                'evolution_recusou',
                self::FALHA_DEFINITIVA,
            );
        }

        Log::warning('Menu interativo recusado; fallback textual enviado.', [
            'outbox_id' => $registro->id,
            'message_key' => $registro->message_key,
            'motivo' => $motivo,
        ]);

        return $this->concluir(
            $registro,
            $resposta,
            'Menu interativo recusado; fallback textual enviado. '.$motivo,
        );
    }

    /**
     * Recoloca na fila o que ficou pendente e o que ficou preso em `processing`.
     *
     * Devolve as linhas prontas para o comando despachar. Não envia nada aqui:
     * quem chama decide se o job roda inline (fila sync) ou é empurrado para o
     * worker.
     *
     * @return list<WhatsAppOutboxMessage>
     */
    public function recover(int $limit = 200): array
    {
        $limite = max(1, $limit);
        $minutos = max(1, (int) config('whatsapp.outbox.stale_minutes', 10));

        WhatsAppOutboxMessage::query()
            ->stale($minutos)
            ->update([
                'status' => WhatsAppOutboxMessage::PENDING,
                'locked_at' => null,
                'available_at' => now(),
                'last_error' => 'Execução anterior não terminou (worker interrompido).',
                'last_error_code' => 'orfao',
            ]);

        return WhatsAppOutboxMessage::query()
            ->due()
            ->orderBy('id')
            ->limit($limite)
            ->get()
            ->all();
    }

    /**
     * Reposiciona uma linha da DLQ para nova tentativa.
     */
    public function replay(WhatsAppOutboxMessage $registro, int $attempts = 0): WhatsAppOutboxMessage
    {
        $registro->forceFill([
            'status' => WhatsAppOutboxMessage::PENDING,
            'attempts' => max(0, $attempts),
            'available_at' => now(),
            'locked_at' => null,
            'last_error' => null,
            'last_error_code' => null,
        ])->save();

        return $registro->refresh();
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    protected function concluir(
        WhatsAppOutboxMessage $registro,
        array $resposta,
        ?string $nota = null,
    ): WhatsAppOutboxMessage {
        $providerId = $this->providerMessageId($resposta);

        $registro->forceFill([
            'status' => WhatsAppOutboxMessage::SENT,
            'sent_at' => now(),
            'locked_at' => null,
            'provider_message_id' => $providerId,
            // `sent` com nota registra que a mensagem saiu por um caminho
            // alternativo (fallback textual). Não é DLQ: a linha foi entregue.
            'last_error' => $nota,
            'last_error_code' => $nota === null ? null : 'interativo_recusado_fallback',
            'available_at' => null,
        ])->save();

        $clinica = $this->tenants->require();

        $this->registrarHistorico(
            $clinica,
            (string) $registro->telefone,
            (string) $registro->remote_jid,
            $registro->body,
            $resposta,
            $registro->message_key,
            $registro->correlation_id,
            WhatsAppDeliveryStatus::SENT,
        );

        return $registro->refresh();
    }

    protected function falhar(
        WhatsAppOutboxMessage $registro,
        string $mensagem,
        string $codigo,
        string $tipo,
    ): WhatsAppOutboxMessage {
        $tentativas = (int) $registro->attempts;
        $maximo = max(1, (int) ($registro->max_attempts ?: config('whatsapp.outbox.max_attempts', 5)));

        $definitiva = $tipo !== self::FALHA_RETRY || $tentativas >= $maximo;

        if ($definitiva) {
            $registro->forceFill([
                'status' => WhatsAppOutboxMessage::DEAD,
                'locked_at' => null,
                'last_error' => $mensagem,
                'last_error_code' => $codigo,
            ])->save();
        } else {
            $registro->forceFill([
                'status' => WhatsAppOutboxMessage::PENDING,
                'locked_at' => null,
                'available_at' => now()->addSeconds($this->backoff($tentativas)),
                'last_error' => $mensagem,
                'last_error_code' => $codigo,
            ])->save();
        }

        Log::warning('Mensagem do WhatsApp não saiu pela outbox.', [
            'outbox_id' => $registro->id,
            'message_key' => $registro->message_key,
            'tentativa' => $tentativas,
            'max_tentativas' => $maximo,
            'status' => $registro->status,
            'codigo' => $codigo,
            'motivo' => $mensagem,
        ]);

        return $registro->refresh();
    }

    /**
     * Guarda no histórico a mensagem que a Evolution aceitou.
     *
     * A chave é o id do WhatsApp quando ele veio; sem ele, a chave da própria
     * outbox (prefixo `local-`/`idem-`), que nunca colide com um id real. O
     * índice único de `whatsapp_messages` absorve o caso de o webhook chegar
     * antes, e nesse caso só completamos o status de entrega.
     *
     * @param  array<string, mixed>  $resposta
     */
    public function registrarHistorico(
        Clinic $clinica,
        string $telefone,
        string $jid,
        ?string $texto,
        array $resposta,
        ?string $fallbackKey = null,
        ?string $correlationId = null,
        ?string $deliveryStatus = WhatsAppDeliveryStatus::SENT,
    ): WhatsAppMessage {
        $chave = $this->providerMessageId($resposta)
            ?? $fallbackKey
            ?? 'local-'.substr(hash('sha256', $clinica->id.'|'.$jid.'|'.$texto.'|'.microtime(true)), 0, 64);

        try {
            return WhatsAppMessage::create([
                'message_key' => $chave,
                'from_me' => true,
                'event' => EvolutionPayload::EVENTO_MENSAGEM,
                'message_type' => 'conversation',
                'remote_jid' => $jid,
                'body' => $texto,
                'delivery_status' => $deliveryStatus,
                'correlation_id' => $correlationId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existente = WhatsAppMessage::query()
                ->where('message_key', $chave)
                ->where('from_me', true)
                ->firstOrFail();

            $existente->forceFill(array_filter([
                'delivery_status' => $deliveryStatus,
                'correlation_id' => $correlationId,
            ], static fn (mixed $valor): bool => $valor !== null))->save();

            return $existente;
        }
    }

    protected function chaveDeIdempotencia(
        Clinic $clinica,
        string $numero,
        string $texto,
        ?string $idempotencyKey,
    ): string {
        if (is_string($idempotencyKey) && trim($idempotencyKey) !== '') {
            // Guardar o texto do cliente como chave permitiria chave gigante e
            // caractere de controle em índice único. O hash mantém a
            // idempotência sem carregar o valor para dentro do banco.
            return 'idem-'.substr(hash('sha256', trim($idempotencyKey)), 0, 64);
        }

        return 'local-'.substr(hash('sha256', $clinica->id.'|'.$numero.'|'.$texto.'|'.Str::uuid()), 0, 64);
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    protected function providerMessageId(array $resposta): ?string
    {
        $chave = $resposta['key']['id'] ?? null;

        return is_string($chave) && $chave !== '' ? $chave : null;
    }

    protected function normalizarTelefone(string $telefone): string
    {
        $numero = preg_replace('/\D/', '', $telefone);

        if ($numero === null || $numero === '') {
            throw new \InvalidArgumentException('Telefone inválido para a outbox do WhatsApp.');
        }

        return $numero;
    }

    protected function backoff(int $tentativa): int
    {
        $base = max(1, (int) config('whatsapp.outbox.retry_base_seconds', 15));
        $maximo = max($base, (int) config('whatsapp.outbox.retry_max_seconds', 300));

        return (int) min($base * (2 ** max(0, $tentativa - 1)), $maximo);
    }

    protected function pareceTimeout(ConnectionException $exception): bool
    {
        $mensagem = strtolower($exception->getMessage());

        return str_contains($mensagem, 'timed out')
            || str_contains($mensagem, 'timeout')
            || str_contains($mensagem, 'curl error 28');
    }

    protected function resumir(string $texto, int $limite = 500): string
    {
        return mb_strlen($texto) > $limite ? mb_substr($texto, 0, $limite).'…' : $texto;
    }
}
