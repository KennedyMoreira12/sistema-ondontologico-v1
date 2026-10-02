<?php

namespace Tests\Feature;

use App\Models\Central\Clinic;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\TenantConnection;
use App\Models\Central\User;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use App\Services\Tenant\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Onboarding de uma clínica, de ponta a ponta.
 *
 * Esta parte nasceu sem nenhum teste e, quando exercitada de verdade, quebrou
 * em três lugares: `--path` do `migrate` recebendo string, `db:seed` que não
 * aceita `--path` nesta versão do Laravel, e o provisionamento sem transação,
 * que deixava clínica órfã no banco central quando algo falhava no meio. O
 * teste existe para que onboarding seja caminho testado, e não só código novo.
 */
class ProvisionamentoClinicaTest extends TestCase
{
    use RefreshDatabase;

    protected string $tenantsDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Os bancos das clínicas são arquivos; o teste não pode escrever dentro
        // de `database/tenants` enquanto a suíte roda.
        $this->tenantsDir = storage_path('framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'tenants-'.uniqid());

        Config::set('tenancy.tenants_directory', $this->tenantsDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tenantsDir.DIRECTORY_SEPARATOR.'*') ?: [] as $arquivo) {
            @unlink($arquivo);
        }

        @rmdir($this->tenantsDir);

        parent::tearDown();
    }

    public function test_cria_clinica_com_banco_proprio_admin_e_assinatura(): void
    {
        $resultado = $this->provisionar();

        $clinica = $resultado['clinic'];

        $this->assertDatabaseHas('clinics', [
            'id' => $clinica->id,
            'name' => 'Clínica Aurora',
            'email' => 'aurora@teste.test',
        ]);

        // Banco da clínica: arquivo próprio, com o schema de clínica inteiro.
        $conexao = TenantConnection::query()->where('clinic_id', $clinica->id)->firstOrFail();

        $this->assertSame('sqlite', $conexao->driver);
        $this->assertStringEndsWith('.sqlite', $conexao->database_name);
        $this->assertFileExists($conexao->database_name);
        $this->assertNotNull($conexao->last_migrated_at);

        // Cada clínica recebe o seu arquivo: duas clínicas não podem colidir.
        $this->assertStringContainsString(
            'clinic_'.str_pad((string) $clinica->id, 6, '0', STR_PAD_LEFT),
            $conexao->database_name,
        );

        $tabelas = $this->tabelasDaClinica($clinica);

        $this->assertContains('pacientes', $tabelas);
        $this->assertContains('appointments', $tabelas);
        $this->assertContains('roles', $tabelas);

        // Admin no CENTRAL, com o papel `admin` dentro do banco da clínica.
        $admin = $resultado['user'];

        $this->assertInstanceOf(User::class, $admin);
        $this->assertSame($clinica->id, $admin->clinic_id);
        $this->assertSame('aurora@teste.test', $admin->email);
        $this->assertTrue($admin->is_active);

        $this->conectar($clinica);
        $this->assertTrue($admin->fresh()->hasRole('admin'));

        // Papéis e permissões semeados: é o que dá ao admin acesso a tudo.
        $this->assertTenantDatabaseCount('roles', 6);
        $this->assertEqualsCanonicalizing(
            ['admin', 'auxiliar', 'estoque', 'financeiro', 'dentista', 'recepcao'],
            DB::connection('tenant')->table('roles')->pluck('name')->all(),
        );
        $totalPermissoes = DB::connection('tenant')->table('permissions')->count();
        $adminId = DB::connection('tenant')->table('roles')->where('name', 'admin')->value('id');

        // Papéis compartilham permissões, então há mais vínculos que permissões.
        // O que não pode faltar é o admin com todas elas.
        $this->assertGreaterThanOrEqual(
            $totalPermissoes,
            DB::connection('tenant')->table('role_permission')->count(),
        );
        $this->assertSame(
            $totalPermissoes,
            DB::connection('tenant')->table('role_permission')->where('role_id', $adminId)->count(),
            'O papel admin precisa receber todas as permissões da clínica.',
        );

        // Catálogo básico presente, senão a clínica nasce sem procedimento nenhum.
        $this->assertGreaterThan(0, DB::connection('tenant')->table('procedures')->count());
        $this->assertGreaterThan(0, DB::connection('tenant')->table('tooth_conditions')->count());

        // Assinatura coerente com o plano.
        $assinatura = Subscription::query()->where('clinic_id', $clinica->id)->firstOrFail();

        $this->assertSame('trialing', $assinatura->status);
        $this->assertNotNull($assinatura->trial_ends_at);
        $this->assertSame('trial', $clinica->status);
    }

    public function test_o_nome_do_banco_vem_do_prefixo_configurado(): void
    {
        Config::set('tenancy.database_prefix', 'odonto_');
        Config::set('tenancy.database_padding', 4);

        $resultado = $this->provisionar();

        $this->assertStringContainsString('odonto_0001', $resultado['connection']->database_name);
    }

    public function test_falha_no_meio_nao_deixa_clinica_orfa_no_central(): void
    {
        // A falha precisa acontecer DEPOIS da clínica ser gravada, senão o teste
        // não prova nada. Apontar o diretório dos bancos para dentro de um
        // arquivo faz o `mkdir` do banco da clínica falhar com o registro
        // central já escrito — que é exatamente o cenário que deixava órfão.
        $bloqueio = $this->tenantsDir.'.bloqueio';
        file_put_contents($bloqueio, 'isto e um arquivo, nao um diretorio');

        Config::set('tenancy.tenants_directory', $bloqueio.DIRECTORY_SEPARATOR.'tenants');

        try {
            $this->provisionar();

            $this->fail('O provisionamento deveria ter falhado.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('diretório', $exception->getMessage());
        } finally {
            @unlink($bloqueio);
        }

        $this->assertSame(0, Clinic::withoutTrashed()->count(), 'Sobrou clinica orfa no banco central.');
        $this->assertSame(0, TenantConnection::query()->count(), 'Sobrou conexao orfa no banco central.');
        $this->assertSame(0, User::query()->count(), 'Sobrou usuario orfao no banco central.');
        $this->assertSame(0, Subscription::query()->count(), 'Sobrou assinatura orfa no banco central.');
    }

    public function test_cada_clinica_recebe_um_banco_distinto(): void
    {
        $primeira = $this->provisionar();
        $segunda = $this->provisionar(email: 'borealis@teste.test', nome: 'Clínica Borealis');

        $this->assertNotSame(
            $primeira['connection']->database_name,
            $segunda['connection']->database_name,
        );

        // O mesmo paciente criado em cada clínica não pode se misturar.
        $this->conectar($primeira['clinic']);
        DB::connection('tenant')->table('pacientes')->insert(['nome' => 'Só da Aurora', 'status' => 'ativo']);

        $this->conectar($segunda['clinic']);
        $this->assertSame(0, DB::connection('tenant')->table('pacientes')->count());
    }

    public function test_plano_sem_avaliacao_deixa_a_clinica_ativa(): void
    {
        $pago = Plan::query()->create([
            'name' => 'Pago',
            'slug' => 'pago',
            'monthly_price' => 199,
            'trial_days' => 0,
            'is_active' => true,
        ]);

        $resultado = $this->provisionar(plano: $pago);

        $this->assertSame('active', $resultado['clinic']->status);
        $this->assertNull($resultado['clinic']->trial_ends_at);
        $this->assertSame('active', $resultado['subscription']->status);
    }

    public function test_conexao_da_clinica_cai_no_servidor_padrao_sem_credencial_no_env(): void
    {
        // Um install novo não precisa declarar `TENANT_DB_*` no `.env`: o `env()`
        // devolve `null` e a clínica precisa nascer apontando para o MySQL da
        // própria máquina. O default do `config()` não cobre esse caso — ele só
        // vale para chave AUSENTE, e a chave existe com valor nulo. Com o
        // default perdido a conexão nascia com `host` nulo e o `tenant:create`
        // morria com "Database hosts array is empty".
        $this->configurarServidorTenant(driver: 'mysql', host: null, port: null, username: null, password: null);

        $conexao = $this->registrarConexao($this->criarClinicaCentral());

        $this->assertSame('127.0.0.1', $conexao->database_host);
        $this->assertSame(3306, $conexao->database_port);
        $this->assertSame('root', $conexao->database_username);
        $this->assertSame('', $conexao->database_password);

        // O que quebrava de fato não era o valor gravado, era o config que sai
        // daqui: é o host vazio que o connector do MySQL recusa.
        $config = $conexao->toConnectionConfig();

        $this->assertNotEmpty(array_filter((array) ($config['host'] ?? null)));
    }

    public function test_conexao_da_clinica_usa_o_servidor_configurado(): void
    {
        $this->configurarServidorTenant(
            driver: 'mysql',
            host: 'db.interno',
            port: 3307,
            username: 'odontologia',
            password: 'segredo',
        );

        $conexao = $this->registrarConexao($this->criarClinicaCentral());

        $this->assertSame('db.interno', $conexao->database_host);
        $this->assertSame(3307, $conexao->database_port);
        $this->assertSame('odontologia', $conexao->database_username);
        $this->assertSame('segredo', $conexao->database_password);
    }

    public function test_nome_do_banco_no_mysql_nao_usa_o_diretorio_de_arquivos(): void
    {
        // O caminho em `tenancy.tenants_directory` é exclusivo do sqlite: no
        // MySQL o que identifica a clínica é o nome do schema.
        $this->configurarServidorTenant(driver: 'mysql', host: '127.0.0.1', port: 3306, username: 'root', password: '');

        $clinica = $this->criarClinicaCentral();
        $conexao = $this->registrarConexao($clinica);

        $this->assertSame(
            config('tenancy.database_prefix').str_pad((string) $clinica->id, (int) config('tenancy.database_padding'), '0', STR_PAD_LEFT),
            $conexao->database_name,
        );
    }

    protected function configurarServidorTenant(
        string $driver,
        ?string $host,
        ?int $port,
        ?string $username,
        ?string $password,
    ): void {
        Config::set('database.connections.tenant', [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
        ]);
    }

    protected function criarClinicaCentral(): Clinic
    {
        return Clinic::query()->create([
            'name' => 'Clínica Aurora',
            'email' => 'aurora@teste.test',
            'status' => 'trial',
            'is_active' => true,
        ]);
    }

    /**
     * `registerConnection` é protected porque só o provisionador o chama, mas é
     * o único lugar que decide com que servidor a clínica nasce. Exercitá-lo
     * pelo `provision()` exigiria um MySQL de verdade para o `createDatabase` e
     * o `migrate`, que a suíte não pode exigir — e é justamente por não passar
     * por ali que a suíte continuava verde enquanto o caminho real quebrava.
     */
    protected function registrarConexao(Clinic $clinica): TenantConnection
    {
        return (new \ReflectionMethod(TenantProvisioner::class, 'registerConnection'))
            ->invoke(app(TenantProvisioner::class), $clinica);
    }

    /**
     * @return array{clinic: Clinic, user: User, connection: TenantConnection, subscription: Subscription, password: string}
     */
    protected function provisionar(
        string $nome = 'Clínica Aurora',
        string $email = 'aurora@teste.test',
        ?Plan $plano = null,
    ): array {
        $plan = $plano ?? Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter-'.uniqid(),
            'monthly_price' => 149,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        return app(TenantProvisioner::class)->provision([
            'name' => $nome,
            'email' => $email,
            'admin_name' => 'Dra Aurora',
            'password' => 'senha-teste-123',
            'plan' => $plan,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function tabelasDaClinica(Clinic $clinica): array
    {
        $this->conectar($clinica);

        // No sqlite oDoctrine lista como `main.pacientes`: o prefixo é o schema,
        // não a tabela, então fica de fora para as asserts lerem o nome real.
        return array_map(
            fn (string $tabela): string => str_contains($tabela, '.')
                ? substr($tabela, (int) strrpos($tabela, '.') + 1)
                : $tabela,
            DB::connection('tenant')->getSchemaBuilder()->getTableListing(),
        );
    }

    protected function conectar(Clinic $clinica): void
    {
        app(TenantManager::class)->set($clinica);
        app(TenantDatabaseManager::class)->connectFor($clinica->refresh());
    }
}
