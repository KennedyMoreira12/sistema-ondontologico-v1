<?php

namespace App\Services\Tenant;

use App\Models\Central\Clinic;
use Closure;
use Throwable;

/**
 * Executa um trecho de código no banco de UMA clínica e devolve o processo ao
 * estado anterior.
 *
 * Jobs, comandos e a outbox recebem apenas ids: o worker não tem sessão, não
 * tem usuário e não tem clínica "atual". Este é o único lugar que transforma
 * um id em conexão, para que a reconexão não seja copiada em cada job e cada
 * job novo esqueça de restaurar a conexão anterior.
 *
 * Restaurar é obrigatório: em teste e em processo de longa duração (worker),
 * deixar a conexão apontada para a clínica A faz o próximo job da clínica B
 * escrever no banco errado — o vazamento que o projeto inteiro evita.
 */
class TenantRunner
{
    public function __construct(
        protected TenantDatabaseManager $databases,
        protected TenantManager $tenants,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(Clinic): T  $callback
     * @return T
     */
    public function run(Clinic|int $clinic, Closure $callback): mixed
    {
        $anterior = $this->tenants->current();
        $clinic = $clinic instanceof Clinic ? $clinic : $this->find($clinic);

        $this->tenants->set($clinic);
        $this->databases->connectFor($clinic);

        try {
            return $callback($clinic);
        } finally {
            $this->restore($anterior);
        }
    }

    protected function find(int $clinicId): Clinic
    {
        return Clinic::query()->findOrFail($clinicId);
    }

    protected function restore(?Clinic $anterior): void
    {
        if ($anterior === null) {
            $this->tenants->forget();
            $this->databases->forget();

            return;
        }

        $this->tenants->set($anterior);

        try {
            $this->databases->connectFor($anterior->refresh());
        } catch (Throwable) {
            // A clínica anterior pode ter sido desativada no meio do caminho.
            // Nesse caso não há o que restaurar, e insistir derrubaria o job
            // atual por um problema que não é dele.
            $this->tenants->forget();
            $this->databases->forget();
        }
    }
}
