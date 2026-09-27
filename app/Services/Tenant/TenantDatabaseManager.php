<?php

namespace App\Services\Tenant;

use App\Exceptions\TenantConnectionInactive;
use App\Models\Central\Clinic;
use App\Models\Central\TenantConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aponta a conexão `tenant` para o banco da clínica em atendimento.
 *
 * Purge antes de reconfigurar e reconecta depois: sem o purge o PDO já aberto
 * continuaria apontando para o banco do tenant anterior, que é exatamente o
 * vazamento entre clínicas que este projeto precisa impedir.
 *
 * A conexão DEFAULT não é trocada. Todo model de clínica estende TenantModel,
 * que já fixa `$connection = 'tenant'`; manter o default no central garante
 * que sessão, cache, fila e models centrais nunca caiam no banco de um
 * paciente por acidente.
 *
 * Esta classe assume FPM (um processo por requisição, um tenant por processo).
 * Se um dia houver Octane ou um worker de fila, `forget()` precisa rodar no
 * reset do processo — ver TenantServiceProvider.
 */
class TenantDatabaseManager
{
    public function connect(TenantConnection $connection): void
    {
        DB::purge('tenant');

        Config::set('database.connections.tenant', $connection->toConnectionConfig());

        DB::reconnect('tenant');

        $this->assertReachable();
    }

    /**
     * Conecta no banco da clínica para uso em requisição.
     *
     * Conexão desligada bloqueia aqui, e não no controller: desativar a clínica
     * precisa valer para o painel, para a API e para qualquer job novo, sem que
     * cada ponto de entrada lembre de checar.
     *
     * @param  bool  $allowInactive  Só os comandos de operador passam `true`:
     *                               migrar ou semear o banco de uma clínica
     *                               desligada é justamente o que se faz antes de
     *                               religá-la.
     */
    public function connectFor(Clinic $clinic, bool $allowInactive = false): void
    {
        $connection = $clinic->tenantConnection;

        if ($connection === null) {
            throw new RuntimeException(
                "A clínica [{$clinic->name}] não tem o banco configurado (tenant_connections)."
            );
        }

        if (! $allowInactive && ! $connection->isActive()) {
            throw TenantConnectionInactive::forClinic($clinic->name);
        }

        $this->connect($connection);
    }

    public function disconnect(): void
    {
        DB::disconnect('tenant');
        DB::setDefaultConnection(config('database.default'));
    }

    /**
     * Deixa a conexão de volta na central. Usado no fim de comandos, jobs e
     * testes, para não vazar o tenant para a próxima operação.
     */
    public function forget(): void
    {
        DB::purge('tenant');
        Config::set('database.connections.tenant', $this->blankConfig());
    }

    /**
     * @return array<string, mixed>
     */
    protected function blankConfig(): array
    {
        return array_merge(
            [
                'driver' => config('database.connections.tenant.driver', 'mysql'),
                'database' => null,
                'prefix' => '',
                'prefix_indexes' => true,
                'foreign_key_constraints' => true,
            ],
            $this->serverKeys(),
        );
    }

    /**
     * O connector do sqlite interpreta a presença da chave `host` como lista
     * de hosts e falha, então as chaves de servidor só entram para drivers
     * que realmente as usam.
     *
     * @return array<string, mixed>
     */
    protected function serverKeys(): array
    {
        if (config('database.connections.tenant.driver') === 'sqlite') {
            return [];
        }

        return [
            'host' => null,
            'port' => null,
            'username' => null,
            'password' => null,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'engine' => null,
        ];
    }

    /**
     * Falhar aqui é melhor do que servir a tela errada: se o banco da clínica
     * não responde, o operador precisa saber na hora.
     */
    protected function assertReachable(): void
    {
        try {
            DB::connection('tenant')->getPdo();
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Não foi possível conectar no banco da clínica: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }
}
