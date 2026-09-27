<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Schema do banco tenant para os testes.
 *
 * Rodar as 42 migrations de tenant a cada teste custava quase um segundo por
 * teste, e sqlite em memória não serve: o `TenantDatabaseManager` faz purge
 * antes de reconectar (para não vazar o banco da clínica anterior), o que
 * destruiria um banco em memória. A saída é migrar UMA vez para um arquivo
 * template e copiar esse arquivo por teste — a cópia custa milissegundos.
 *
 * O template é reconstruído sozinho quando alguma migration fica mais nova que
 * ele, para não servir schema velho no meio do desenvolvimento.
 */
final class TenantSchema
{
    protected static ?string $templatePath = null;

    protected static int $templateBuiltAt = 0;

    /**
     * Garante o template em disco e devolve o caminho dele.
     */
    public static function templatePath(): string
    {
        $path = storage_path('framework/testing/tenant_template.sqlite');

        if (self::$templatePath === $path && self::templateEstaAtualizado($path)) {
            return $path;
        }

        self::construir($path);

        return $path;
    }

    /**
     * Copia o template para um arquivo exclusivo deste teste.
     */
    public static function freshDatabasePath(): string
    {
        $destination = storage_path('framework/testing/tenant_'.uniqid().'.sqlite');

        self::prepararDiretorio($destination);

        if (! copy(self::templatePath(), $destination)) {
            throw new RuntimeException("Não foi possível copiar o schema tenant para {$destination}.");
        }

        return $destination;
    }

    protected static function construir(string $path): void
    {
        self::prepararDiretorio($path);
        @unlink($path);
        touch($path);

        $configAnterior = config('database.connections.tenant');

        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'prefix_indexes' => true,
            'foreign_key_constraints' => true,
        ]);

        DB::purge('tenant');

        $saida = new BufferedOutput;
        $codigo = Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ], $saida);

        DB::purge('tenant');
        Config::set('database.connections.tenant', $configAnterior);

        if ($codigo !== 0) {
            throw new RuntimeException(
                "As migrations de tenant falharam (exit {$codigo}):\n".$saida->fetch()
            );
        }

        self::$templatePath = $path;
        self::$templateBuiltAt = self::migrationMaisRecente();
    }

    protected static function templateEstaAtualizado(string $path): bool
    {
        return is_file($path)
            && self::$templateBuiltAt > 0
            && self::$templateBuiltAt >= self::migrationMaisRecente();
    }

    protected static function migrationMaisRecente(): int
    {
        $maisRecente = 0;

        foreach (glob(database_path('migrations/tenant/*.php')) ?: [] as $migration) {
            $maisRecente = max($maisRecente, (int) filemtime($migration));
        }

        return $maisRecente;
    }

    protected static function prepararDiretorio(string $path): void
    {
        $diretorio = dirname($path);

        if (! is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }
    }
}
