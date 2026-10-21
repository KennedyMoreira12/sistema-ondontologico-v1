<?php

namespace App\Services\WhatsApp;

use App\Models\Central\Clinic;
use App\Models\Central\UsageMetric;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * A cota mensal de IA do plano.
 *
 * Sem isto, `ai_agent` seria uma feature ligada/desligada sem contagem: bastava
 * o plano ter o recurso para o gasto com o provedor ser ilimitado, e a clínica
 * que conversasse mais no mês consumiria o orçamento de todas. A cota é o que
 * transforma "tem IA" em "tem X mensagens de IA".
 *
 * A contagem vive em `usage_metrics` (banco CENTRAL), não no banco da clínica:
 * é consumo da plataforma, não dado clínico, e o operador precisa enxergá-la
 * sem abrir o banco de ninguém.
 *
 * O plano é lido pela ASSINATURA (`latestSubscription`), como faz o
 * `CheckPlanFeature`, e não por `Clinic::hasFeature()` — aquele método lê
 * `$this->currentPlan` como atributo e o Eloquent o resolve como relação,
 * estourando `LogicException`. A assinatura é a mesma fonte que já libera a
 * rota; usá-la aqui mantém a cota e o acesso decidindo pelo mesmo estado.
 *
 * `reservar()` é o ponto delicado. Checar e depois incrementar em dois passos
 * deixaria duas requisições concorrentes passarem pelo mesmo "ainda há cota".
 * O lock por clínica serializa a decisão; quem perde espera e relê o contador
 * já atualizado, e a cota não estoura por corrida.
 */
class CotaIA
{
    /**
     * Há cota para mais uma chamada?
     *
     * Diferente de `reservar()`: NÃO incrementa nada e serve para a resposta
     * rápida "pode seguir?" antes de montar a chamada. Quem vai de fato gastar
     * usa `reservar()`, que é atômico.
     */
    public function disponivel(Clinic $clinica): bool
    {
        if (! $this->temFeature($clinica)) {
            return false;
        }

        $limite = $this->limiteMensal($clinica);

        if ($limite === null) {
            return true;
        }

        return $this->consumo($clinica) < $limite;
    }

    /**
     * Reserva uma chamada da cota e devolve se ela foi concedida.
     *
     * Incrementa `ai_requests` já na reserva — e não só no sucesso — porque a
     * decisão de chamar o modelo é o que gera o custo, e uma reserva que
     * esperasse a resposta poderia estourar sob concorrência. O consumo real de
     * tokens entra depois, separado, em `registrarTokens()`.
     */
    public function reservar(Clinic $clinica): bool
    {
        if (! $this->temFeature($clinica)) {
            return false;
        }

        $limite = $this->limiteMensal($clinica);

        try {
            return Cache::lock($this->chave($clinica), 10)->block(5, function () use ($clinica, $limite): bool {
                if ($limite !== null && $this->consumo($clinica) >= $limite) {
                    return false;
                }

                UsageMetric::record($clinica, 'ai_requests');

                return true;
            });
        } catch (LockTimeoutException $exception) {
            // Não conseguiu a trava em 5s: recusa em vez de liberar sem contar.
            // Liberar aqui furaria a cota exatamente no pico, que é quando o
            // lock demora.
            Log::warning('Não foi possível reservar a cota de IA a tempo.', [
                'clinica' => $clinica->id,
                'motivo' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Soma os tokens gastos na chamada que já foi reservada.
     */
    public function registrarTokens(Clinic $clinica, int $entrada, int $saida): void
    {
        if ($entrada > 0) {
            UsageMetric::record($clinica, 'ai_input_tokens', $entrada);
        }

        if ($saida > 0) {
            UsageMetric::record($clinica, 'ai_output_tokens', $saida);
        }
    }

    /**
     * Quantas chamadas de IA a clínica já consumiu no mês corrente.
     */
    public function consumo(Clinic $clinica): int
    {
        return (int) $clinica->usageMetrics()
            ->where('period', UsageMetric::currentPeriod())
            ->value('ai_requests');
    }

    /**
     * Teto mensal do plano, ou `null` quando não há limite configurado.
     *
     * `null` significa "sem limite", e não "sem cota": a feature já foi checada
     * antes. Um plano que vende IA sem contagem é decisão do negócio, não um
     * defeito a bloquear.
     */
    public function limiteMensal(Clinic $clinica): ?int
    {
        $limite = $clinica->latestSubscription?->plan
            ?->featureLimits('ai_agent')['ai_messages_per_month'] ?? null;

        return is_numeric($limite) ? (int) $limite : null;
    }

    /**
     * A feature vem da ASSINATURA ATIVA, que é exatamente o que a rota já
     * exige. `Subscription::hasFeature()` cobre as duas condições de uma vez:
     * assinatura em vigor E plano com o recurso.
     */
    protected function temFeature(Clinic $clinica): bool
    {
        return $clinica->latestSubscription?->hasFeature('ai_agent') === true;
    }

    protected function chave(Clinic $clinica): string
    {
        return 'ia:cota:'.$clinica->id;
    }
}
