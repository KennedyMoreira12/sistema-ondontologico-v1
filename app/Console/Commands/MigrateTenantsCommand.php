<?php

namespace App\Console\Commands;

use App\Models\Central\Clinic;
use App\Services\Tenant\TenantDatabaseProvisioner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Aplica as migrations de clínica em todos os tenants (ou em um só).
 *
 * Rodar isto depois de mergear uma migration é obrigatório: sem ele, a
 * clínica nova nasce com o schema velho e a clínica antiga não recebe a
 * coluna nova.
 */
class MigrateTenantsCommand extends Command
{
    protected $signature = 'tenants:migrate
        {--clinic= : ID da clínica específica (padrão: todas)}
        {--seed : Também rodar os seeders da clínica}
        {--force : Não pedir confirmação}';

    protected $description = 'Roda as migrations de clínica em uma ou em todas as clínicas';

    public function handle(TenantDatabaseProvisioner $provisioner): int
    {
        $clinics = Clinic::query()
            ->when($this->option('clinic'), fn ($query, $id) => $query->whereKey($id))
            // `whereHas`, e não `whereNotNull('tenant_connections.database_name')`:
            // a coluna é de outra tabela e, sem join, isso vira erro de SQL.
            ->whereHas('tenantConnection')
            ->with('tenantConnection')
            ->get();

        if ($clinics->isEmpty()) {
            $this->components->warn('Nenhuma clínica encontrada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirmToProceed($clinics->count())) {
            return self::FAILURE;
        }

        $falhas = [];

        foreach ($clinics as $clinic) {
            $sucesso = true;

            $this->components->task("Migrando [{$clinic->name}] ({$clinic->tenantConnection->database_name})", function () use ($provisioner, $clinic, &$sucesso): bool {
                try {
                    $provisioner->migrate($clinic, seed: (bool) $this->option('seed'));

                    return true;
                } catch (Throwable $exception) {
                    // `components->task()` devolve void, então o resultado precisa
                    // sair por referência: sem isto a contagem de falhas fica
                    // sempre em zero e o comando reporta sucesso em cima de erro.
                    $sucesso = false;
                    $this->newLine();
                    $this->components->error($exception->getMessage());

                    return false;
                }
            });

            if (! $sucesso) {
                $falhas[] = $clinic->name;
            }
        }

        if ($falhas !== []) {
            $this->newLine();
            $this->components->error(count($falhas).' clínica(s) não foram migradas: '.implode(', ', $falhas));
            $this->components->error('O banco da clínica citada acima não recebeu as migrations.');

            return self::FAILURE;
        }

        $this->components->info('Todas as clínicas foram migradas.');

        return self::SUCCESS;
    }

    protected function confirmToProceed(int $count): bool
    {
        return $this->confirm("Aplicar migrations em {$count} clínica(s)?", true);
    }
}
