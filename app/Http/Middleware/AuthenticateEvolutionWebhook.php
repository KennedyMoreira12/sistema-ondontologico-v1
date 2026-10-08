<?php

namespace App\Http\Middleware;

use App\Exceptions\TenantConnectionInactive;
use App\Models\Central\Clinic;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica o webhook da Evolution API e conecta no banco da clínica dona da
 * instância.
 *
 * A Evolution não assina o webhook: não existe HMAC nem challenge, e o header
 * `apikey` que ela manda é o token da instância, que também vai no corpo — num
 * campo que qualquer um pode escrever num corpo forjado. Por isso a checagem tem
 * DUAS portas, e as duas precisam bater:
 *
 * 1. `EVOLUTION_WEBHOOK_SECRET` no header `X-Evolution-Secret`, configurado como
 *    header fixo no webhook da instância. Sem ele, qualquer pessoa que descubra a
 *    URL escreve num endpoint que cria paciente e agendamento.
 * 2. O `apikey` do corpo tem que ser igual ao token da clínica resolvida pelo
 *    nome da instância. Isso impede que o segredo de uma instância seja usado
 *    para injetar mensagem na fila de outra: o segredo é o mesmo para todas, o
 *    token não.
 *
 * Fail-closed nas duas. Sem segredo configurado a rota recusa todo POST, em vez
 * de aceitar tudo — o mesmo caminho do `MetaWebhookController`, e pela mesma
 * razão: uma rota pública sem assinatura é o pior buraco possível num sistema
 * multi-tenant.
 *
 * A clínica vem do CORPO, nunca da URL. Uma rota `/webhooks/evolution/{clinic}`
 * deixaria quem conhece o nome da instância — que é dado público no payload do
 * próprio webhook — escrever no banco de outra clínica.
 */
class AuthenticateEvolutionWebhook
{
    /**
     * Chave em que a clínica resolvida fica disponível para o controller.
     */
    public const REQUEST_ATTRIBUTE = 'evolution_clinic';

    public function __construct(
        protected TenantManager $tenants,
        protected TenantDatabaseManager $databases,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->segredoConfere($request)) {
            Log::warning('Webhook da Evolution com segredo invalido.', [
                'ip' => $request->ip(),
                'instance' => $request->json('instance'),
            ]);

            return response()->json(['message' => 'Assinatura do webhook invalida.'], 403);
        }

        $clinica = $this->resolverClinica($request);

        if ($clinica === null) {
            Log::warning('Webhook da Evolution para uma instancia sem clinica.', [
                'instance' => $request->json('instance'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Instancia nao encontrada.'], 404);
        }

        if (! $this->tokenConfere($request, $clinica)) {
            Log::warning('Webhook da Evolution com apikey de outra instancia.', [
                'instance' => $request->json('instance'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Token da instancia invalido.'], 403);
        }

        if (! $clinica->isAccessible()) {
            // 403 e nao 404: a clinica existe e a Evolution sobe o webhook por
            // causa disso, entao ela precisa saber que e problema de acesso e
            // nao de URL.
            return response()->json(['message' => 'A clinica desta instancia nao esta com acesso liberado.'], 403);
        }

        $this->tenants->set($clinica);

        try {
            $this->databases->connectFor($clinica);
        } catch (TenantConnectionInactive) {
            return response()->json(['message' => 'O acesso ao banco desta clinica esta desativado.'], 403);
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $clinica);

        return $next($request);
    }

    /**
     * `hash_equals` em vez de `===`: o tempo de resposta de `===` vazaria o
     * quanto do segredo já casava. E o segredo vazio recusa tudo — sem ele a rota
     * fica fechada, que é o que o fail-closed quer dizer.
     */
    protected function segredoConfere(Request $request): bool
    {
        $esperado = config('services.evolution.webhook_secret');
        $esperado = is_string($esperado) ? trim($esperado) : '';

        if ($esperado === '') {
            return false;
        }

        $informado = (string) $request->header('X-Evolution-Secret', '');

        return $informado !== '' && hash_equals($esperado, $informado);
    }

    /**
     * A clínica dona da instância.
     *
     * `withEvolution()` corta o decrypt de token de clínica que não tem integração
     * nenhuma. O filtro precisa ser o mesmo que `Clinic::hasEvolutionIntegration()`
     * decide, senão uma clínica passaria por uma checagem e falharia na outra.
     */
    protected function resolverClinica(Request $request): ?Clinic
    {
        $instancia = $request->json('instance');

        if (! is_string($instancia) || $instancia === '') {
            return null;
        }

        return Clinic::query()
            ->withEvolution()
            ->where('evolution_instance', $instancia)
            ->with('tenantConnection')
            ->first();
    }

    /**
     * O `apikey` do corpo é o token da instância que gerou o evento. Comparar com
     * o da clínica impede o cruzamento entre instâncias; sem isso, o segredo
     * global bastaria para escrever mensagem na fila errada.
     */
    protected function tokenConfere(Request $request, Clinic $clinica): bool
    {
        $informado = $this->tokenDaInstancia($request);

        if (! is_string($informado) || $informado === '') {
            return false;
        }

        return hash_equals((string) $clinica->evolution_token, $informado);
    }

    /**
     * Token da instância que gerou o evento, em qualquer um dos formatos que a
     * Evolution manda: no JSON do corpo (`apikey`), no header `apikey`, ou no
     * header `Authorization` (com ou sem o prefixo "Bearer "). Nunca confundir
     * com `LJ_INTEGRATION_TOKEN`, que é o segredo do n8n chamando a API interna;
     * este é o token da instância, que vem da Evolution.
     */
    protected function tokenDaInstancia(Request $request): ?string
    {
        $corpo = $request->json('apikey');

        if (is_string($corpo) && $corpo !== '') {
            return $corpo;
        }

        $headerApiKey = $request->header('apikey');

        if (is_string($headerApiKey) && $headerApiKey !== '') {
            return $headerApiKey;
        }

        $autorizacao = $request->header('Authorization');

        if (is_string($autorizacao) && $autorizacao !== '') {
            return preg_replace('/^(?:Bearer|Basic)\s+/i', '', $autorizacao);
        }

        return null;
    }
}
