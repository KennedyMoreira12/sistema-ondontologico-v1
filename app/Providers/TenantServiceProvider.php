<?php

namespace App\Providers;

use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
        $this->app->singleton(TenantDatabaseManager::class);
    }

    public function boot(): void
    {
        /**
         * `php artisan migrate` só enxerga esta pasta, então o banco central
         * nunca recebe tabela de paciente por engano. As migrations de clínica
         * ficam em database/migrations/tenant e rodam por clínica, na conexão
         * `tenant`, via `php artisan tenants:migrate`.
         */
        $this->loadMigrationsFrom(database_path('migrations/central'));

        /**
         * Um processo de fila atende requests de clínicas diferentes. Sem
         * esquecer o tenant no fim do job, o próximo job herda o banco do
         * anterior. Este callback roda ao final de cada requisição/job.
         *
         * Em teste a limpeza é desligada de propósito: o corpo do teste
         * continua usando a clínica depois que a resposta foi devolvida (por
         * exemplo num `$modelo->refresh()`), e sem a conexão ele rebenta em
         * `database` nulo. A conexão é recriada por `apontarConexaoTenant` a
         * cada teste, então nada vaza entre eles.
         */
        if ($this->app->bound('events') && ! $this->app->runningUnitTests()) {
            $this->app['events']->listen(
                RequestHandled::class,
                function (): void {
                    $this->app->make(TenantDatabaseManager::class)->forget();
                    $this->app->make(TenantManager::class)->forget();
                },
            );
        }
    }
}
