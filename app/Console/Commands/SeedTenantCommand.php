<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantDatabaseProvisioner;
use App\Services\Tenant\TenantManager;
use Database\Seeders\tenant\DemoFisioterapiaSeeder;
use Database\Seeders\tenant\DemoSeeder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Roda os seeders de catálogo dentro do banco de uma clínica (papéis, permissões,
 * convênios, procedimentos, status). Útil depois de criar a clínica na mão.
 */
class SeedTenantCommand extends Command
{
    protected $signature = 'tenants:seed
        {--clinic= : ID da clínica (padrão: a primeira clínica ativa)}
        {--demo : Semeia também dados de demonstração (pacientes, agenda, financeiro)}
        {--demo-fisio : Demonstração da vertical de fisioterapia (catálogo, avaliação, evolução e pacotes de sessões)}';

    protected $description = 'Roda os seeders de catálogo no banco de uma clínica';

    public function handle(
        TenantDatabaseProvisioner $provisioner,
        TenantManager $tenants,
        TenantDatabaseManager $databases,
    ): int {
        $clinic = Clinic::query()
            ->when($this->option('clinic'), fn ($query, $id) => $query->whereKey($id))
            ->where('is_active', true)
            ->whereHas('tenantConnection')
            ->with('tenantConnection')
            ->first();

        if ($clinic === null) {
            $this->components->error('Nenhuma clínica ativa com banco configurado. Use `tenant:create` antes.');

            return self::FAILURE;
        }

        try {
            $provisioner->migrate($clinic, seed: true);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Seeders aplicados em [{$clinic->name}].");

        if ($this->option('demo')) {
            $this->semeDemo($clinic, $tenants, $databases);
        }

        if ($this->option('demo-fisio')) {
            $this->semeDemoFisioterapia($clinic, $tenants, $databases);
        }

        return self::SUCCESS;
    }

    /**
     * O DemoSeeder fica fora do `TenantDatabaseSeeder` de propósito: ele cria
     * pacientes, agenda e financeiro de mentira, e nenhuma clínica real deve
     * receber isso por rodar um seed de catálogo. Por isso a execução é
     * explícita, via `--demo`.
     *
     * O contexto de tenant é reestabelecido aqui porque `migrate()` faz
     * `forget()` no `finally` — ele existe para o `tenants:migrate` não vazar a
     * clínica anterior para a próxima do loop, e não para quem quer emendar
     * outro passo logo depois.
     */
    protected function semeDemo(
        Clinic $clinica,
        TenantManager $tenants,
        TenantDatabaseManager $databases,
    ): void {
        try {
            $tenants->set($clinica);
            $databases->connectFor($clinica, allowInactive: true);

            (new DemoSeeder)->setContainer(app())->run();
        } catch (Throwable $exception) {
            $this->components->error('Falha ao semear a demonstração: '.$exception->getMessage());

            return;
        } finally {
            $databases->forget();
            $tenants->forget();
        }

        $this->components->info("Demonstração semeada em [{$clinica->name}].");
    }

    /**
     * Demonstração da vertical de fisioterapia.
     *
     * Separada do `DemoSeeder` odontológico porque os dois não podem rodar na
     * mesma clínica: aquele cria odontograma e tratamento de canal; este ajusta
     * o catálogo para fisioterapia e semeia ficha de avaliação, evolução por
     * sessão e pacote de sessões.
     *
     * O `--demo-fisio` também mexe no catálogo da clínica (remove procedimentos
     * e especialidades odontológicas), então é ainda mais explícito que o
     * `--demo`: use só em ambiente de demonstração.
     */
    protected function semeDemoFisioterapia(
        Clinic $clinica,
        TenantManager $tenants,
        TenantDatabaseManager $databases,
    ): void {
        try {
            $tenants->set($clinica);
            $databases->connectFor($clinica, allowInactive: true);

            (new DemoFisioterapiaSeeder)->setContainer(app())->run();
        } catch (Throwable $exception) {
            $this->components->error('Falha ao semear a demonstração de fisioterapia: '.$exception->getMessage());

            return;
        } finally {
            $databases->forget();
            $tenants->forget();
        }

        $this->components->info("Demonstração de fisioterapia semeada em [{$clinica->name}].");
    }
}
