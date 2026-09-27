<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantSchema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Arquivo sqlite exclusivo deste teste, apontado pela conexão `tenant`.
     */
    protected string $tenantFile;

    /**
     * As migrations foram separadas em `migrations/central` e
     * `migrations/tenant`, então a pasta `migrations/` raiz está vazia e o
     * `migrate:fresh` do RefreshDatabase não encontra nada por padrão. A pasta
     * central é registrada no migrator assim que o container existe, para que o
     * RefreshDatabase migre o banco central; o banco tenant entra por conta
     * própria, com o schema já pronto copiado de um template.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->app->make('migrator')->path(database_path('migrations/central'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBancoTenant();
    }

    protected function tearDown(): void
    {
        if (isset($this->tenantFile) && is_file($this->tenantFile)) {
            @unlink($this->tenantFile);
        }

        parent::tearDown();
    }

    /**
     * Copia o schema de tenant para um arquivo deste teste e aponta a conexão
     * `tenant` para ele. Os testes com mais de uma clínica sobrescrevem isto
     * pelo `CreatesTenants`, que precisa de um banco por clínica.
     */
    protected function prepararBancoTenant(): void
    {
        $this->tenantFile = TenantSchema::freshDatabasePath();

        $this->apontarConexaoTenant($this->tenantFile);
    }

    protected function apontarConexaoTenant(string $database): void
    {
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'prefix_indexes' => true,
            'foreign_key_constraints' => true,
        ]);

        DB::purge('tenant');
    }

    /**
     * Depois de uma requisição o TenantServiceProvider limpa a conexão tenant
     * (RequestHandled) e a config fica sem `database`. Asserções precisam
     * reconectar no arquivo do teste antes de consultar.
     */
    protected function reconectarTenantParaAssercao(): void
    {
        if (Config::get('database.connections.tenant.database') === null) {
            $this->apontarConexaoTenant($this->tenantFile);
        }
    }

    /**
     * Asserções no banco da clínica.
     *
     * `assertDatabaseHas('tenant.pacientes', ...)` NÃO funciona: o Laravel lê o
     * prefixo como nome de schema e consulta a conexão default, que é a central.
     * A conexão se informa como terceiro argumento, e como errar aqui significa
     * passar o teste olhando o banco errado, ficam em helpers.
     *
     * @param  array<string, mixed>  $data
     */
    protected function assertTenantDatabaseHas(string $table, array $data): void
    {
        $this->reconectarTenantParaAssercao();

        $this->assertDatabaseHas($table, $data, 'tenant');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertTenantDatabaseMissing(string $table, array $data): void
    {
        $this->reconectarTenantParaAssercao();

        $this->assertDatabaseMissing($table, $data, 'tenant');
    }

    protected function assertTenantDatabaseCount(string $table, int $count): void
    {
        $this->reconectarTenantParaAssercao();

        $this->assertDatabaseCount($table, $count, 'tenant');
    }
}
