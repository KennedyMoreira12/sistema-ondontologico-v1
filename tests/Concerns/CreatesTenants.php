<?php

namespace Tests\Concerns;

use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\Central\User;
use App\Models\Role;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use App\Support\IntegrationAbilities;
use Tests\Support\TenantSchema;

/**
 * Infraestrutura dos testes que precisam de MAIS DE UMA clínica.
 *
 * Cada clínica ganha um arquivo sqlite próprio (copiado do schema template) e
 * a conexão `tenant` é apontada para cada uma na vez, exatamente como o
 * middleware faz em produção. Para o caso comum de uma clínica só, `UsesTestTenant`
 * é mais direto.
 *
 * O `TestCase` base já resolve o banco central e o schema tenant, então aqui
 * não é preciso rodar migration nenhuma.
 */
trait CreatesTenants
{
    use SubscribesPlan;

    /**
     * @var array<int, string>
     */
    protected array $tenantFiles = [];

    /**
     * Cria uma clínica completa: registro central, conexão, banco próprio e um
     * usuário administrador. Também devolve um token de integração em texto puro,
     * que só existe aqui — no banco só fica o hash.
     *
     * @return array{clinic: Clinic, user: ?User, token: string}
     */
    protected function criarClinica(
        string $name = 'Clínica Teste',
        string $email = 'contato@clinica.test',
        bool $comAdmin = true,
    ): array {
        $clinic = Clinic::create([
            'name' => $name,
            'email' => $email,
            'status' => 'active',
            'is_active' => true,
        ]);

        $path = $this->makeTenantFile();

        $clinic->tenantConnection()->create([
            'driver' => 'sqlite',
            'database_name' => $path,
            'is_active' => true,
            'last_migrated_at' => now(),
        ]);

        $this->conectar($clinic);

        // A API do WhatsApp só abre para clínica com o recurso no plano, e token
        // sem abilities não faz nada. Um token de teste nasce com acesso
        // completo; o que restringe token é o que interessa testar, e isso é
        // feito no teste, não na infraestrutura.
        $this->assinarPlanoComWhatsapp($clinic);

        $token = IntegrationToken::generateToken();

        IntegrationToken::create([
            'clinic_id' => $clinic->id,
            'name' => 'n8n',
            'token_hash' => IntegrationToken::hashToken($token),
            'abilities' => [IntegrationAbilities::WILDCARD],
        ]);

        $user = null;

        if ($comAdmin) {
            $user = User::create([
                'clinic_id' => $clinic->id,
                'name' => 'Admin '.$name,
                'email' => 'admin@'.$clinic->id.'.test',
                'password' => 'senha-teste-123',
                'is_active' => true,
            ]);

            $user->assignRole(Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrador']));
        }

        return ['clinic' => $clinic, 'user' => $user, 'token' => $token];
    }

    /**
     * Conecta no banco da clínica e marca o TenantManager, que é o que o
     * middleware faz em produção.
     */
    protected function conectar(Clinic $clinic): void
    {
        app(TenantManager::class)->set($clinic);
        app(TenantDatabaseManager::class)->connectFor($clinic->refresh());
    }

    protected function makeTenantFile(): string
    {
        $path = TenantSchema::freshDatabasePath();

        $this->tenantFiles[] = $path;

        return $path;
    }

    public function tearDown(): void
    {
        foreach ($this->tenantFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->tenantFiles = [];

        parent::tearDown();
    }
}
