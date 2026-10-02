<?php

namespace App\Services\Tenant;

use App\Models\Central\Clinic;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\TenantConnection;
use App\Models\Central\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Onboarding de uma clínica: registro central, banco próprio, papéis, admin e
 * assinatura. É o mesmo caminho usado pelo painel da empresa e pelo comando
 * `tenant:create` — nada de lógica de tenancy só dentro de um controller.
 */
class TenantProvisioner
{
    public function __construct(
        protected TenantDatabaseProvisioner $databases,
        protected TenantDatabaseManager $connections,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{clinic: Clinic, user: User, connection: TenantConnection, subscription: Subscription, password: string}
     */
    public function provision(array $attributes): array
    {
        $plan = $attributes['plan'] ?? Plan::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();

        $connection = null;

        try {
            return DB::transaction(function () use ($attributes, $plan, &$connection): array {
                $clinic = Clinic::create([
                    'name' => $attributes['name'],
                    'legal_name' => $attributes['legal_name'] ?? null,
                    'document' => $attributes['document'] ?? null,
                    'email' => $attributes['email'],
                    'phone' => $attributes['phone'] ?? null,
                    'whatsapp' => $attributes['whatsapp'] ?? null,
                    'primary_color' => $attributes['primary_color'] ?? '#0b7285',
                    'secondary_color' => $attributes['secondary_color'] ?? '#0ca678',
                    'status' => $plan->trial_days > 0 ? 'trial' : 'active',
                    'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                    'is_active' => true,
                ]);

                $connection = $this->registerConnection($clinic);

                $this->databases->createDatabase($connection);
                $this->databases->migrate($clinic, seed: true);

                $this->connections->connectFor($clinic);

                try {
                    $adminRole = $this->ensureAdminRole();
                    $password = (string) ($attributes['password'] ?? Str::password(12));
                    $user = $this->createAdministrator($clinic, $attributes, $adminRole, $password);
                } finally {
                    $this->connections->forget();
                }

                $subscription = $this->startSubscription($clinic, $plan);

                return [
                    'clinic' => $clinic,
                    'user' => $user,
                    'connection' => $connection,
                    'subscription' => $subscription,
                    'password' => $password,
                ];
            });
        } catch (Throwable $exception) {
            // A transão desfaz clínica, conexão, usuário e assinatura, mas o banco
            // da clínica é um arquivo (ou um schema) próprio: sem esta limpeza uma
            // tentativa que falha deixa lixo, e a próxima colide no e-mail.
            $this->descartarBanco($connection);

            throw $exception;
        }
    }

    protected function descartarBanco(?TenantConnection $connection): void
    {
        if ($connection === null) {
            return;
        }

        try {
            $this->databases->dropDatabase($connection);
        } catch (Throwable) {
            // Perder o resto do banco é o pior dos casos; a mensagem original da
            // falha é a que interessa, então o erro do descarte não a substitui.
        }
    }

    protected function registerConnection(Clinic $clinic): TenantConnection
    {
        $driver = config('database.connections.tenant.driver', 'mysql');

        $databaseName = $driver === 'sqlite'
            ? rtrim((string) config('tenancy.tenants_directory'), '\\/')
                .DIRECTORY_SEPARATOR.$this->databases->generateDatabaseName($clinic->id).'.sqlite'
            : $this->databases->generateDatabaseName($clinic->id);

        /**
         * O `??` é obrigatório aqui, e o default do `config()` não serve: a chave
         * existe sempre, porque `config/database.php` a monta com `env()`. Com
         * `TENANT_DB_HOST` não definida o `env()` devolve `null`, o default do
         * `config()` é ignorado (só vale para chave AUSENTE) e a clínica nascia
         * com `database_host` nulo — o `toConnectionConfig()` jogava isso no
         * connector como lista de hosts vazia e o `tenant:create` morria com
         * "Database hosts array is empty". Com `??` o `null` cai no fallback.
         */
        return $clinic->tenantConnection()->create([
            'driver' => $driver,
            'database_host' => config('database.connections.tenant.host') ?? '127.0.0.1',
            'database_port' => (int) (config('database.connections.tenant.port') ?? 3306),
            'database_name' => $databaseName,
            'database_username' => config('database.connections.tenant.username') ?? 'root',
            'database_password' => (string) (config('database.connections.tenant.password') ?? ''),
        ]);
    }

    /**
     * O papel `admin` precisa existir ANTES do usuário ser criado, porque é o
     * único que enxerga todo o sistema. As permissões vêm do RolePermissionSeeder.
     */
    protected function ensureAdminRole(): Role
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['label' => 'Administrador'],
        );

        if ($role->permissions()->count() === 0) {
            $role->permissions()->sync(
                Permission::query()->pluck('id')
            );
        }

        return $role->fresh();
    }

    protected function createAdministrator(Clinic $clinic, array $attributes, Role $adminRole, string $password): User
    {
        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => $attributes['admin_name'] ?? $attributes['name'],
            'email' => $attributes['admin_email'] ?? $attributes['email'],
            'password' => $password,
            'phone' => $attributes['phone'] ?? null,
            'is_active' => true,
        ]);

        $user->assignRole($adminRole);

        return $user;
    }

    protected function startSubscription(Clinic $clinic, Plan $plan): Subscription
    {
        $trialEndsAt = $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null;

        return $clinic->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => $trialEndsAt !== null ? 'trialing' : 'active',
            'started_at' => now(),
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => $trialEndsAt ?? now(),
            'current_period_end' => ($trialEndsAt ?? now())->addMonth(),
        ]);
    }

    public function destroy(Clinic $clinic, bool $dropDatabase = true): void
    {
        $connection = $clinic->tenantConnection;

        if ($connection === null) {
            throw new RuntimeException("A clínica [{$clinic->name}] não tem banco registrado.");
        }

        if ($dropDatabase) {
            // O purge vem ANTES do drop: o PDO aberto trava o arquivo do sqlite
            // no Windows, e a exceção disso é um `unlink` que não acontece.
            DB::purge('tenant');

            $this->databases->dropDatabase($connection);
        }

        $clinic->tenantConnection()->delete();
        $clinic->subscriptions()->delete();
        $clinic->users()->delete();
        $clinic->delete();

        DB::purge('tenant');
    }
}
