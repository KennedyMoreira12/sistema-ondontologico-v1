<?php

namespace Tests\Feature;

use App\Exceptions\TenantConnectionInactive;
use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\Central\User;
use App\Models\Paciente;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;
use Throwable;

/**
 * O teste mais importante do projeto: duas clínicas, um mesmo processo, e
 * nenhuma enxergando o banco da outra.
 *
 * Vale rodar mesmo "só pra ver" depois de mexer em conexão, middleware ou
 * model — a maior parte dos bugs de multi-tenant passa pelo sintaxe e só
 * aparece aqui.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_cada_clinica_tem_seu_proprio_banco(): void
    {
        ['clinic' => $a] = $this->criarClinica('Clínica A', 'a@clinica.test');
        Paciente::create(['clinica_id' => $a->id, 'nome' => 'Paciente A', 'status' => 'ativo']);

        ['clinic' => $b] = $this->criarClinica('Clínica B', 'b@clinica.test');

        $this->assertNotSame(
            $a->tenantConnection->database_name,
            $b->tenantConnection->database_name,
            'As clínicas estão apontando para o mesmo banco, o que anula todo o isolamento.',
        );

        $this->conectar($b);

        // A consulta vai direto na conexão `tenant`: as helpers de banco do
        // Laravel usam a conexão default, e ali `pacientes` não existe — a
        // asserção passaria por engano sem checar o banco certo.
        $this->assertFalse(
            DB::connection('tenant')->table('pacientes')->where('nome', 'Paciente A')->exists(),
            'Um paciente da Clínica A apareceu no banco da Clínica B.',
        );
        $this->assertSame(0, Paciente::count());
    }

    public function test_paciente_criado_na_clinica_a_nao_aparece_na_clinica_b(): void
    {
        ['clinic' => $a] = $this->criarClinica('Clínica A', 'a@clinica.test');
        $this->conectar($a);
        Paciente::create(['clinica_id' => $a->id, 'nome' => 'Somente A', 'status' => 'ativo']);

        ['clinic' => $b] = $this->criarClinica('Clínica B', 'b@clinica.test');
        $this->conectar($b);

        $this->assertSame(
            [],
            Paciente::pluck('nome')->all(),
            'Um paciente da Clínica A apareceu na Clínica B.',
        );
    }

    public function test_central_guarda_as_duas_clinicas_e_o_tenant_nao(): void
    {
        ['clinic' => $a] = $this->criarClinica('Clínica A', 'a@clinica.test');
        ['clinic' => $b] = $this->criarClinica('Clínica B', 'b@clinica.test');

        $this->assertSame(2, Clinic::count(), 'O banco central deve ver as duas clínicas.');
        $this->assertSame(2, User::count());

        // O banco da clínica B não pode enxergar o registro central da clínica A.
        $this->conectar($a);
        $this->assertFalse(DB::connection('tenant')->getSchemaBuilder()->hasTable('clinics'));
    }

    public function test_token_de_uma_clinica_nao_autentica_acesso_a_outra(): void
    {
        ['clinic' => $a, 'token' => $tokenA] = $this->criarClinica('Clínica A', 'a@clinica.test');
        ['clinic' => $b, 'token' => $tokenB] = $this->criarClinica('Clínica B', 'b@clinica.test');

        Paciente::create(['clinica_id' => $b->id, 'nome' => 'Paciente B', 'status' => 'ativo']);

        // O token da Clínica A precisa conectar no banco da A, não no da B.
        $this->withHeader('X-Integration-Token', $tokenA)
            ->getJson(route('api.whatsapp.info'))
            ->assertOk()
            ->assertJsonPath('clinica.id', $a->id);

        $this->conectar($b);
        $this->assertSame(['Paciente B'], Paciente::pluck('nome')->all());
    }

    public function test_token_invalido_e_recusado(): void
    {
        $this->criarClinica('Clínica A', 'a@clinica.test');

        $this->withHeader('X-Integration-Token', 'token-que-nao-existe')
            ->getJson(route('api.whatsapp.info'))
            ->assertUnauthorized();
    }

    public function test_requisicao_sem_token_e_recusada(): void
    {
        $this->criarClinica('Clínica A', 'a@clinica.test');

        $this->getJson(route('api.whatsapp.info'))->assertUnauthorized();
    }

    public function test_token_revogado_nao_autentica(): void
    {
        ['token' => $token] = $this->criarClinica('Clínica A', 'a@clinica.test');

        $this->conectar(Clinic::first());
        IntegrationToken::first()->revoke();

        $this->withHeader('X-Integration-Token', $token)
            ->getJson(route('api.whatsapp.info'))
            ->assertUnauthorized();
    }

    public function test_clinica_suspensa_nao_autentica_pelo_token(): void
    {
        ['clinic' => $clinic, 'token' => $token] = $this->criarClinica('Clínica A', 'a@clinica.test');

        $clinic->update(['status' => 'suspended']);

        $this->withHeader('X-Integration-Token', $token)
            ->getJson(route('api.whatsapp.info'))
            ->assertForbidden();
    }

    public function test_conexao_desativada_bloqueia_o_painel(): void
    {
        ['clinic' => $clinica, 'user' => $usuario] = $this->criarClinica(
            'Clínica A',
            'a@clinica.test',
            comAdmin: true,
        );

        $clinica->tenantConnection->update(['is_active' => false]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_conexao_desativada_bloqueia_a_api_sem_invalidar_o_token(): void
    {
        ['clinic' => $clinica, 'token' => $token] = $this->criarClinica('Clínica A', 'a@clinica.test');

        $clinica->tenantConnection->update(['is_active' => false]);

        // 403, e não 401: o token continua válido, quem está desligado é o banco.
        $this->withHeader('X-Integration-Token', $token)
            ->getJson(route('api.whatsapp.info'))
            ->assertForbidden();

        $this->assertTrue(
            IntegrationToken::first()->isActive(),
            'O token não deveria ter sido invalidado por uma conexão desativada.',
        );
    }

    public function test_conectar_em_conexao_desativada_falha_com_erro_proprio(): void
    {
        ['clinic' => $clinica] = $this->criarClinica('Clínica A', 'a@clinica.test');

        $clinica->tenantConnection->update(['is_active' => false]);

        // Exceção própria, e não RuntimeException genérica: o middleware
        // precisa distinguir "clínica desativada" (403) de "banco fora do ar".
        $this->expectException(TenantConnectionInactive::class);

        app(TenantDatabaseManager::class)->connectFor($clinica->refresh());
    }

    public function test_migrar_clinica_desativada_continua_possivel(): void
    {
        ['clinic' => $clinica] = $this->criarClinica('Clínica A', 'a@clinica.test');

        $clinica->tenantConnection->update(['is_active' => false]);

        // Desligar a clínica não pode impedir o operador de atualizar o schema
        // dela — essa é justamente a manutenção feita antes de religar.
        $this->artisan('tenants:migrate', ['--clinic' => $clinica->id, '--force' => true])
            ->assertSuccessful();
    }

    public function test_limpar_conexao_nao_deixa_o_tenant_vazado_para_o_proximo_tenant(): void
    {
        ['clinic' => $a] = $this->criarClinica('Clínica A', 'a@clinica.test');
        Paciente::create(['clinica_id' => $a->id, 'nome' => 'Paciente A', 'status' => 'ativo']);

        app(TenantDatabaseManager::class)->forget();
        app(TenantManager::class)->forget();

        $this->assertNull(app(TenantManager::class)->current());

        // A conexão fica sem banco configurado. O que importa é que uma
        // consulta falhe alto, e não devolva os dados da clínica anterior para
        // quem ainda não resolveu tenant nenhum.
        $this->assertNull(config('database.connections.tenant.database'));

        $vazou = false;

        try {
            $vazou = Paciente::where('nome', 'Paciente A')->exists();
        } catch (Throwable $e) {
            $vazou = false;
        }

        $this->assertFalse($vazou, 'Após limpar o tenant, uma consulta devolveu dado da clínica anterior.');
    }
}
