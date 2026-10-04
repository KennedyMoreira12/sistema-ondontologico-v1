<?php

namespace App\Exceptions;

use App\Http\Controllers\Api\EvolutionWebhookController;
use RuntimeException;
use Throwable;

/**
 * O evento não pôde ser entregue ao n8n.
 *
 * Existe para separar "não consegui falar com o n8n" de "entreguei". Sem essa
 * distinção o webhook da Evolution não tem resposta honesta: ou responde 200 e
 * aceita que o bot nunca viu a mensagem, ou responde 503 e a Evolution reentrega
 * para sempre sem chance de melhora.
 *
 * `recuperavel` é o que decide a resposta HTTP. Falha de rede, timeout e 5xx do
 * n8n melhoram sozinhos na reentrega. `N8N_WEBHOOK_URL` vazia ou 4xx não melhoram
 * nunca, e reentregar dez vezes só gasta o orçamento de retry da Evolution e
 * atrasa as mensagens das outras clínicas — então o evento é marcado como
 * falho e a Evolution recebe 200.
 *
 * @see EvolutionWebhookController::handle()
 */
class N8nIndisponivel extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $recuperavel = true,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function semUrl(): self
    {
        return new self('N8N_WEBHOOK_URL não está configurada.', recuperavel: false);
    }

    /**
     * Resposta do n8n que não vai melhorar com reentrega.
     */
    public static function respostaInvalida(int $status, string $corpo): self
    {
        return new self("HTTP {$status}: {$corpo}", recuperavel: false);
    }
}
