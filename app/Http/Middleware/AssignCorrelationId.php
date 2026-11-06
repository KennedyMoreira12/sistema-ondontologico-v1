<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dá a cada requisição de API um identificador de correlação.
 *
 * O mesmo id aparece no header da resposta, no log da requisição e nas linhas
 * gravadas (`whatsapp_messages.correlation_id` / `whatsapp_outbox.correlation_id`).
 * Sem ele, ligar "o paciente diz que não recebeu" a uma linha de log de um
 * webhook entre milhares é adivinhação; com ele, é um filtro.
 *
 * O header do cliente é aceito quando tem formato seguro — o n8n pode propagar
 * o id da própria execução, o que encurta a investigação dos dois lados. Header
 * inválido é substituído, nunca usado cru: ele vai para log e para resposta.
 */
class AssignCorrelationId
{
    public const ATTRIBUTE = 'correlation_id';

    public const HEADER = 'X-Correlation-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $this->normalizar($request->header(self::HEADER)) ?? (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $id);

        Log::withContext(['correlation_id' => $id]);

        $response = $next($request);

        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    protected function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '' || mb_strlen($valor) > 64) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9._:-]+$/', $valor) === 1 ? $valor : null;
    }
}
