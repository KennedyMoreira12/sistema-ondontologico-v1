<?php

namespace App\Services\Tenant;

use App\Models\Central\Clinic;
use App\Models\Central\TenantConnection;
use Database\Seeders\tenant\DatabaseSeeder as TenantDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Operações de banco de uma clínica: criar, migrar, semear e destruir.
 *
 * Fica num service (e não no command) porque o painel da empresa vai
 * onboardingar clínica pela interface, e a interface não deve carregar
 * `Artisan::call` dentro de um controller.
 */
class TenantDatabaseProvisioner
{
    public function __construct(
        protected TenantDatabaseManager $databases,
        protected TenantManager $tenants,
    ) {}

    /**
     * Cria o arquivo de banco (sqlite) ou o schema (mysql) de uma clínica.
     * Mysql resolve por `CREATE DATABASE`; sqlite apenas garante o arquivo.
     */
    public function createDatabase(TenantConnection $connection): void
    {
        if ($connection->driver === 'sqlite') {
            $path = $connection->database_name;

            if (! str_ends_with($path, '.sqlite')) {
                throw new RuntimeException(
                    "O caminho do banco sqlite da clínica deve terminar em .sqlite, recebido: {$path}"
                );
            }

            $directory = dirname($path);

            // O `@` é necessário: sem ele o warning do `mkdir` vira exceção antes
            // da mensagem abaixo, e o operador leria "mkdir(): No such file or
            // directory" em vez de saber qual diretório falhou.
            if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException(
                    "Não foi possível criar o diretório {$directory} para o banco da clínica."
                );
            }

            if (! file_exists($path)) {
                touch($path);
            }

            return;
        }

        $name = $this->assertSafeIdentifier($connection->database_name);

        DB::connection('mysql')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    public function dropDatabase(TenantConnection $connection): void
    {
        if ($connection->driver === 'sqlite') {
            if (file_exists($connection->database_name)) {
                unlink($connection->database_name);
            }

            return;
        }

        $name = $this->assertSafeIdentifier($connection->database_name);

        DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$name}`");
    }

    public function migrate(Clinic $clinic, bool $seed = false): void
    {
        $this->tenants->set($clinic);

        // `allowInactive`: atualizar o schema de uma clínica desligada é
        // trabalho de operador legítimo, e é o passo anterior a religá-la.
        $this->databases->connectFor($clinic, allowInactive: true);

        try {
            // `--path` do `migrate` é array; o do `db:seed` não existe nesta
            // versão do Laravel, e é por `--class` que o seed da clínica roda.
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => [database_path('migrations/tenant')],
                '--realpath' => true,
                '--force' => true,
            ]);

            if ($seed) {
                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => TenantDatabaseSeeder::class,
                    '--force' => true,
                ]);
            }

            $clinic->tenantConnection->forceFill(['last_migrated_at' => now()])->save();
        } finally {
            $this->databases->forget();
            $this->tenants->forget();
        }
    }

    public function generateDatabaseName(int $clinicId): string
    {
        $prefix = config('tenancy.database_prefix');
        $padding = (int) config('tenancy.database_padding');

        return $prefix.Str::padLeft((string) $clinicId, $padding, '0');
    }

    /**
     * O nome do banco vem de config e id numérico, nunca do input — mas um
     * backtick ou aspa dentro do nome quebraria o CREATE DATABASE e, pior,
     * permitiria injeção de SQL. Melhor barrar na entrada.
     */
    protected function assertSafeIdentifier(string $name): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException(
                "Nome de banco inválido [{$name}]: use apenas letras, números e underscore."
            );
        }

        return $name;
    }
}
