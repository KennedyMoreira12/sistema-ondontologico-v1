<?php

namespace App\Services;

use App\Exceptions\N8nIndisponivel;
use App\Models\Central\Clinic;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Entrega o evento ao webhook do workflow do n8n.
 *
 * O Laravel entrega o evento JÁ RESOLVIDO — clínica, instância, mensagem, número
 * do paciente — porque o caminho alternativo, o n8n descobrir a dona da mensagem
 * por conta própria, depende de um `CLINICAS_MAP` que mora fora deste
 * repositório e desatualiza em silêncio: a instância muda no painel, o mapa não
 * muda, e a mensagem é descartada sem erro visível. Aqui a fonte da verdade é a
 * coluna `evolution_instance`.
 *
 * O corpo NÃO leva o token de integração da clínica, e essa é uma decisão, não uma
 * falha: `integration_tokens` guarda SHA-256, então o valor em claro não existe
 * no banco e não há como lê-lo. O n8n tem a credencial dele — no header do nó, ou
 * em variable da instância — e é por ela que ele chama `/api/whatsapp/*`.
 *
 * `dispatch()` LANÇA em vez de devolver `false`. Quem chama precisa decidir o que
 * responder à Evolution, e essa decisão depende do motivo da falha: rede e 5xx do
 * n8n melhoram com reentrega, `N8N_WEBHOOK_URL` vazia e 4xx não. Um `false` não
 * carrega essa diferença, e quem o recebesse acabaria respondendo 200 para tudo e
 * perdendo mensagem sem ninguém perceber.
 *
 * Sem `retry`: repetir envio aqui duplicaria o que o paciente recebe. O retry que
 * existe é o da Evolution no webhook de entrada, e é por isso que a dedupe e a
 * reserva de despacho do lado do Laravel importam.
 */
class N8nDispatcher
{
    /**
     * Entrega o evento e marca a mensagem como entregue.
     *
     * @param  array<string, mixed>  $evento
     *
     * @throws N8nIndisponivel
     */
    public function dispatch(Clinic $clinic, WhatsAppMessage $mensagem, array $evento): void
    {
        $url = rtrim((string) config('services.n8n.webhook_url'), '/');

        if ($url === '') {
            $this->marcarComoFalha($mensagem, N8nIndisponivel::semUrl());

            throw N8nIndisponivel::semUrl();
        }

        try {
            $response = Http::timeout((int) config('services.n8n.timeout', 15))
                ->acceptJson()
                ->withHeaders($this->headers())
                ->post($url, $this->payload($clinic, $mensagem, $evento));
        } catch (ConnectionException $exception) {
            // Timeout e conexão recusada são exatamente o caso que a reentrega da
            // Evolution resolve; uma exceção genérica aqui seria tratada como
            // erro definitivo e a mensagem se perderia.
            $falha = new N8nIndisponivel($exception->getMessage(), recuperavel: true, previous: $exception);

            $this->marcarComoFalha($mensagem, $falha);

            throw $falha;
        }

        if ($response->successful()) {
            $mensagem->forceFill([
                'forwarded_at' => now(),
                'forward_error' => null,
            ])->save();

            return;
        }

        // 5xx e 429 são do lado do n8n e melhoram com reentrega. 4xx é
        // configuração errada — webhook apagado, token inválido no nó — e repetir
        // dez vezes só gasta o retry da Evolution.
        $falha = $response->serverError() || $response->status() === 429
            ? new N8nIndisponivel('HTTP '.$response->status().': '.$this->resumir($response->body()))
            : N8nIndisponivel::respostaInvalida($response->status(), $this->resumir($response->body()));

        $this->marcarComoFalha($mensagem, $falha);

        throw $falha;
    }

    /**
     * Corpo entregue ao n8n.
     *
     * `message.id` é o id interno e `message.key` é o id do WhatsApp. São
     * coisas diferentes e o n8n precisa das duas: a primeira para consultar a
     * mensagem depois, a segunda para deduplicar contra o que o WhatsApp
     * manda.
     *
     * @param  array<string, mixed>  $evento
     * @return array<string, mixed>
     */
    protected function payload(Clinic $clinic, WhatsAppMessage $mensagem, array $evento): array
    {
        return [
            'clinic' => [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'whatsapp' => $clinic->whatsapp,
                'evolution_instance' => $clinic->evolution_instance,
                'timezone' => $clinic->timezone,
            ],

            'message' => [
                'id' => $mensagem->id,
                'key' => $mensagem->message_key,
                'from_me' => (bool) $mensagem->from_me,
                'type' => $mensagem->message_type,
                'text' => $mensagem->body,
                'remote_jid' => $mensagem->remote_jid,
                'telefone' => $mensagem->telefone(),
                'push_name' => $mensagem->push_name,
                'sent_at' => $mensagem->sent_at?->toIso8601String(),
            ],

            'event' => $evento,
        ];
    }

    /**
     * Segredo compartilhado entre o Laravel e o n8n, quando existe.
     *
     * Sem ele a chamada segue sem header: o n8n instalado do lado da VPS já é
     * alcançável só pela rede interna, e exigir o segredo deixaria a integração
     * parada em quem não configurou a variável.
     *
     * @return array<string, string>
     */
    protected function headers(): array
    {
        $token = config('services.n8n.webhook_token');

        return is_string($token) && trim($token) !== ''
            ? ['X-N8n-Token' => trim($token)]
            : [];
    }

    protected function marcarComoFalha(WhatsAppMessage $mensagem, N8nIndisponivel $falha): void
    {
        $mensagem->forceFill([
            'forwarded_at' => null,
            'forward_error' => $falha->getMessage(),
        ])->save();

        Log::warning('Não foi possível entregar a mensagem ao n8n.', [
            'whatsapp_messages_id' => $mensagem->id,
            'message_key' => $mensagem->message_key,
            'recuperavel' => $falha->recuperavel,
            'motivo' => $falha->getMessage(),
        ]);
    }

    /**
     * A Evolution e o n8n devolvem corpo de erro grande demais para o log.
     */
    protected function resumir(string $body, int $limite = 500): string
    {
        return mb_strlen($body) > $limite
            ? mb_substr($body, 0, $limite).'…'
            : $body;
    }
}
