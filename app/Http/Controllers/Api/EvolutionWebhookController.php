<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\N8nIndisponivel;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\AuthenticateEvolutionWebhook;
use App\Models\Central\Clinic;
use App\Models\WhatsAppMessage;
use App\Services\N8nDispatcher;
use App\Support\EvolutionPayload;
use App\Support\WhatsAppDeliveryStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de entrada da Evolution API.
 *
 * A autenticação toda — segredo, resolução da clínica pelo nome da instância e
 * conferência do token da instância — acontece no
 * `AuthenticateEvolutionWebhook`, que já deixou a conexão apontada para o banco
 * da clínica. Aqui o trabalho é o do lifecycle: registrar a mensagem uma vez só e
 * repassá-la ao n8n.
 *
 * A rota NÃO tem id de clínica: a clínica vem do `instance` do corpo. Ver o
 * middleware para por que isso não é negociável.
 *
 * A resposta HTTP é o que fecha o contrato com a Evolution, que reentrega o que
 * recebe 5xx (dez vezes, com backoff) e não reentrega 2xx. Por isso ela
 * corresponde a "essa entrega ainda pode melhorar?", e não a "deu certo?":
 *
 * - 200 `dispatched`: o n8n recebeu. Nada a reentregar.
 * - 503 `stored`: a mensagem ficou registrada e o n8n falhou de forma que uma
 *   reentrega resolve. A Evolution volta, e a reserva abaixo refaz o despacho.
 * - 200 `stored`: falha que reentrega não resolve (URL vazia, 4xx do n8n). Seria
 *   dez reentregas inúteis se respondesse 503.
 * - 200 `duplicate`: o mesmo `key.id` já foi entregue ao n8n.
 * - 200 `status_updated`: era `MESSAGES_UPDATE`; o histórico foi atualizado e
 *   o n8n não foi chamado.
 */
class EvolutionWebhookController extends Controller
{
    public function __construct(protected N8nDispatcher $n8n) {}

    public function handle(Request $request): JsonResponse
    {
        /** @var Clinic $clinica */
        $clinica = $request->attributes->get(AuthenticateEvolutionWebhook::REQUEST_ATTRIBUTE);

        $payload = $request->json()->all();
        $evento = EvolutionPayload::evento($payload);
        $correlationId = $request->attributes->get(AssignCorrelationId::ATTRIBUTE);

        if ($evento === null) {
            Log::warning('Webhook da Evolution sem nome de evento.', [
                'instance' => $payload['instance'] ?? null,
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        /**
         * Status de entrega/leitura. Atualiza o histórico e NÃO despacha ao
         * n8n: o workflow já respondeu a essa mensagem, e reenviá-la faria o
         * paciente receber resposta repetida.
         */
        if ($evento === EvolutionPayload::EVENTO_STATUS) {
            $atualizadas = $this->atualizarStatus($payload, is_string($correlationId) ? $correlationId : null);

            return response()->json(['status' => 'status_updated', 'updated' => $atualizadas], 200);
        }

        if (! EvolutionPayload::temMensagem($payload)) {
            $this->registrarEventoSemMensagem($clinica, $evento, $payload);

            return response()->json(['status' => 'ignored'], 200);
        }

        $chave = EvolutionPayload::chave($payload);

        if ($chave === null) {
            Log::warning('Webhook da Evolution com evento de mensagem sem key.id.', [
                'event' => $evento,
                'instance' => $payload['instance'] ?? null,
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        $mensagem = $this->registrarMensagem($clinica, $payload, $chave, $evento, is_string($correlationId) ? $correlationId : null);

        /**
         * A reserva decide entre despachar e dizer `duplicate`, e é ela — não a
         * leitura de `forwarded_at` — que impede o bot de responder duas vezes à
         * mesma mensagem. Reentrega é normal na Evolution, e ela pode reentregar
         * em paralelo: duas requisições que lessem `forwarded_at` antes de
         * qualquer uma escrever veriam as duas `null` e as duas chamariam o n8n.
         *
         * `whereNull('forwarded_at')` no UPDATE é o compare-and-swap: só uma das
         * duas requisições afeta linha. A que perde devolve 200 sem despachar,
         * porque a outra está despachando.
         *
         * O custo: se o processo morrer entre a reserva e a gravação do sucesso, a
         * mensagem fica reservada e nunca mais é entregue. Aceito — perder uma
         * mensagem em crash é melhor que o paciente receber duas respostas do bot.
         */
        if (! $this->reservarParaDespacho($mensagem)) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        try {
            $this->n8n->dispatch($clinica, $mensagem, ['nome' => $evento]);
        } catch (N8nIndisponivel $falha) {
            return response()->json(['status' => 'stored'], $falha->recuperavel ? 503 : 200);
        }

        return response()->json(['status' => 'dispatched'], 200);
    }

    /**
     * Reserva a mensagem para despacho deste processo.
     *
     * @return bool `false` quando outro processo já reservou ou já entregou.
     */
    protected function reservarParaDespacho(WhatsAppMessage $mensagem): bool
    {
        return WhatsAppMessage::query()
            ->whereKey($mensagem->getKey())
            ->whereNull('forwarded_at')
            ->update(['forwarded_at' => now()]) === 1;
    }

    /**
     * Grava a mensagem, ou devolve a que já existia.
     *
     * A dedupe é o índice único `whatsapp_messages_key_unique`, e não uma
     * consulta antes do insert: entre o SELECT e o INSERT cabe uma segunda
     * entrega da Evolution, e as duas passariam. Disputar o insert e perder no
     * índice é o que fecha a corrida.
     *
     * Perder o índice NÃO é fim de atendimento — é o caminho da reentrega que
     * recupera o despacho, então a linha existente é recarregada em vez de
     * devolvida como `null`.
     *
     * `payload` guarda o que o atendimento usa e nada mais. O JSON cru da
     * Evolution traz `mediaKey`, `participant` e identificadores de sessão do
     * Baileys, que nada aqui consome e que um dump do banco da clínica acabaria
     * guardando.
     */
    protected function registrarMensagem(Clinic $clinica, array $payload, string $chave, string $evento, ?string $correlationId = null): WhatsAppMessage
    {
        try {
            return WhatsAppMessage::create([
                'message_key' => $chave,
                'from_me' => EvolutionPayload::enviadaPelaClinica($payload),
                'event' => $evento,
                'message_type' => EvolutionPayload::messageType($payload),
                'remote_jid' => EvolutionPayload::remoteJid($payload),
                'push_name' => EvolutionPayload::pushName($payload),
                'body' => EvolutionPayload::texto($payload),
                'payload' => [
                    'remote_jid_alt' => $payload['data']['key']['remoteJidAlt'] ?? null,
                    'context_info' => $payload['data']['contextInfo']['messageId'] ?? null,
                ],
                'sent_at' => $this->sentAt($payload),
                'correlation_id' => $correlationId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return WhatsAppMessage::query()
                ->where('message_key', $chave)
                ->where('from_me', EvolutionPayload::enviadaPelaClinica($payload))
                ->firstOrFail();
        }
    }

    /**
     * O timestamp do WhatsApp é unix em SEGUNDOS, e o do PHP é em microssegundos.
     * Passar os dois cru para o cast `datetime` grava uma data em 1970 e o
     * histórico da conversa aparece fora de ordem.
     */
    protected function sentAt(array $payload): ?Carbon
    {
        $timestamp = EvolutionPayload::timestamp($payload);

        return $timestamp === null ? null : Carbon::createFromTimestamp($timestamp);
    }

    /**
     * Aplica os eventos `MESSAGES_UPDATE` ao histórico.
     *
     * Devolve quantas linhas mudaram, que é o que o teste e o operador usam
     * para distinguir "evento chegou e não interessava" de "evento não chegou".
     *
     * A mensagem é procurada por `message_key` + `from_me` porque o WhatsApp
     * reusa o mesmo id nos dois sentidos da conversa. Só mensagem da clínica
     * recebe status: o status de uma inbound é do aparelho do paciente e não
     * diz nada sobre a entrega da clínica.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function atualizarStatus(array $payload, ?string $correlationId): int
    {
        $atualizadas = 0;

        foreach (EvolutionPayload::atualizacoesDeStatus($payload) as $atualizacao) {
            $novo = WhatsAppDeliveryStatus::fromEvolution($atualizacao['status']);

            if ($novo === null) {
                continue;
            }

            $mensagem = WhatsAppMessage::query()
                ->where('message_key', $atualizacao['key'])
                ->where('from_me', $atualizacao['from_me'])
                ->first();

            if ($mensagem === null) {
                continue;
            }

            if (! WhatsAppDeliveryStatus::shouldApply($mensagem->delivery_status, $novo)) {
                continue;
            }

            $momento = $atualizacao['timestamp'] !== null
                ? Carbon::createFromTimestamp($atualizacao['timestamp'])
                : now();

            $dados = ['delivery_status' => $novo];

            if ($correlationId !== null && $mensagem->correlation_id === null) {
                $dados['correlation_id'] = $correlationId;
            }

            if (in_array($novo, [WhatsAppDeliveryStatus::DELIVERED, WhatsAppDeliveryStatus::READ], true)
                && $mensagem->delivered_at === null) {
                $dados['delivered_at'] = $momento;
            }

            if ($novo === WhatsAppDeliveryStatus::READ && $mensagem->read_at === null) {
                $dados['read_at'] = $momento;
            }

            $mensagem->forceFill($dados)->save();

            $atualizadas++;
        }

        return $atualizadas;
    }

    /**
     * Eventos que não são mensagem ficam no log, e é só isso.
     *
     * `CONNECTION_UPDATE` é o que responde "por que a clínica parou de receber
     * mensagem", e essa resposta hoje é o log: o painel ainda não mostra o estado
     * do WhatsApp. Quando mostrar, o lugar de ler é daqui, não de um segundo
     * webhook.
     */
    protected function registrarEventoSemMensagem(Clinic $clinica, string $evento, array $payload): void
    {
        if (! in_array($evento, EvolutionPayload::EVENTOS_SEM_MENSAGEM, true)) {
            Log::info('Webhook da Evolution de evento não tratado.', [
                'event' => $evento,
                'clinica' => $clinica->id,
            ]);

            return;
        }

        Log::info('Webhook da Evolution de estado da conexão.', [
            'event' => $evento,
            'clinica' => $clinica->id,
            'instance' => $clinica->evolution_instance,
            'connection' => $payload['data']['connection'] ?? null,
        ]);
    }
}
