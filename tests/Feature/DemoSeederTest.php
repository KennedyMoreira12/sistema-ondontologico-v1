<?php

namespace Tests\Feature;

use App\Models\Central\Clinic;
use App\Models\Central\Plan;
use App\Models\Role;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use App\Services\Tenant\TenantProvisioner;
use Database\Seeders\tenant\DemoDaianeSeeder;
use Database\Seeders\tenant\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O DemoSeeder semia uma clínica inteira: agenda, financeiro, estoque, clínico.
 *
 * O teste mais importante aqui não é "preencheu a demo", e sim `segunda
 * execução não duplica`. Rodar o seeder duas vezes é uso normal — recriar a
 * demo de uma clínica, um environments novo — e a duplicação silenciosa de
 * registro financeiro é o tipo de bug que ninguém percebe até a contabilidade
 * fechar diferente.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected string $tenantsDir;

    protected int $clinicaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantsDir = storage_path('framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'demo-'.uniqid());

        Config::set('tenancy.tenants_directory', $this->tenantsDir);

        // O provisionamento real: cria clínica, banco, papéis, catálogos e admin.
        // Montar a conexão à mão aqui só criaria um banco sem os papéis e
        // catálogos que o DemoSeeder dá como existente.
        $resultado = app(TenantProvisioner::class)->provision([
            'name' => 'Clínica Demo',
            'email' => 'demo@teste.test',
            'admin_name' => 'Dra. Aurora',
            'password' => 'senha-teste-123',
            'plan' => Plan::create([
                'name' => 'Starter',
                'slug' => 'starter-'.uniqid(),
                'monthly_price' => 149,
                'trial_days' => 14,
                'is_active' => true,
            ]),
        ]);

        $this->clinicaId = $resultado['clinic']->id;

        $this->conectar($resultado['clinic']);
    }

    protected function tearDown(): void
    {
        $this->apagarPastaDeBancosDeTeste($this->tenantsDir);

        parent::tearDown();
    }

    public function test_semeia_a_clinica_completa(): void
    {
        (new DemoSeeder)->run();

        $this->assertSame(4, $this->contabilidade('professionals'), 'Dra. Ana, Dr. Carlos, Dr. Paulo e Dr. João.');
        $this->assertSame(12, $this->contabilidade('pacientes'));
        $this->assertSame(18, $this->contabilidade('appointments'));
        $this->assertSame(6, $this->contabilidade('budgets'));
        $this->assertSame(2, $this->contabilidade('payments'));
        $this->assertSame(8, $this->contabilidade('products'));

        // O seeder não pode deixar a clínica pela metade: oROLL de agenda precisa
        // ter quem atenda e onde atender.
        $this->assertSame(9, Role::count());
        $this->assertGreaterThan(0, $this->contabilidade('rooms'));
    }

    public function test_segunda_execucao_nao_duplica_registro_financeiro(): void
    {
        (new DemoSeeder)->run();

        $pagamentos = $this->contabilidade('payments');
        $prescricoes = $this->contabilidade('prescriptions');
        $itens = $this->contabilidade('prescription_items');
        $contas = $this->contabilidade('accounts_receivable');

        (new DemoSeeder)->run();

        $this->assertSame($pagamentos, $this->contabilidade('payments'), 'O pagamento foi duplicado.');
        $this->assertSame($contas, $this->contabilidade('accounts_receivable'), 'A conta a receber foi duplicada.');
        $this->assertSame($prescricoes, $this->contabilidade('prescriptions'), 'A prescrição foi duplicada.');
        $this->assertSame($itens, $this->contabilidade('prescription_items'), 'O item de prescrição foi duplicado.');
    }

    public function test_nenhuma_tabela_cresce_na_segunda_execucao(): void
    {
        (new DemoSeeder)->run();

        $antes = $this->todasAsContagens();

        (new DemoSeeder)->run();

        $depois = $this->todasAsContagens();

        // A auditoria cresce de propósito (registrar que o seeder rodou), então
        // fica de fora da comparação.
        unset($antes['audit_logs'], $depois['audit_logs']);

        foreach ($antes as $tabela => $total) {
            $this->assertSame(
                $total,
                $depois[$tabela],
                "A tabela [{$tabela}] cresceu na segunda execução do DemoSeeder.",
            );
        }
    }

    public function test_o_dentista_titular_da_demo_e_criado(): void
    {
        // A agenda, as anamneses e os planos citam "Dr. João Silva" pelo nome.
        // Sem esta linha, o seeder estoura em `firstOrFail()` bem depois de já
        // ter semeado meia clínica.
        (new DemoSeeder)->run();

        $this->assertTrue(
            DB::connection('tenant')->table('professionals')->where('nome', 'Dr. João Silva')->exists(),
            'O DemoSeeder usa Dr. João Silva por nome e precisa criá-lo.',
        );
    }

    public function test_pagamento_nao_duplica_por_diferenca_de_hora(): void
    {
        // Mesmo dia, hora diferente. A chave do pagamento é o DIA, então passar
        // horas não pode criar uma segunda linha. Com `now()` cru na chave, a
        // busca levava a hora junto e nunca achava o pagamento anterior.
        $this->travelTo('2026-09-26 09:00:00');

        (new DemoSeeder)->run();

        $this->assertSame(2, $this->contabilidade('payments'));

        $this->travelTo('2026-09-26 18:00:00');

        (new DemoSeeder)->run();

        $this->assertSame(2, $this->contabilidade('payments'), 'Pagamento duplicado por diferença de hora no mesmo dia.');

        $this->travelBack();
    }

    public function test_comando_tenants_seed_com_demo_semeia_a_clinica(): void
    {
        // O DemoSeeder precisa ser alcançável por comando: sem isto ele é código
        // que ninguém consegue rodar sem escrever script.
        $this->artisan('tenants:seed', ['--clinic' => $this->clinicaId, '--demo' => true])
            ->assertSuccessful();

        $this->conectar($this->clinica());

        $this->assertSame(12, $this->contabilidade('pacientes'));
        $this->assertSame(18, $this->contabilidade('appointments'));
    }

    public function test_comando_tenants_seed_sem_demo_nao_semeia_dados_de_paciente(): void
    {
        // Seed de catálogo não pode vazar paciente de mentira numa clínica real.
        $this->artisan('tenants:seed', ['--clinic' => $this->clinicaId])
            ->assertSuccessful();

        $this->conectar($this->clinica());

        $this->assertSame(0, $this->contabilidade('pacientes'));
        $this->assertSame(9, Role::count(), 'Os papéis e permissões devem continuar sendo semeados.');
    }

    /**
     * A demo da Daiane cresce a partir da demo de fisioterapia e usa datas
     * relativas a "hoje". Sem a guarda de reexecução, rodar o comando de novo
     * duplicaria agenda, consumo de sessão e baixas de contas a pagar.
     */
    public function test_demo_daiane_nao_duplica_na_segunda_execucao(): void
    {
        (new DemoDaianeSeeder)->run();

        $tabelas = [
            'pacientes', 'appointments', 'payments', 'pacotes_sessao',
            'consumo_sessao', 'suppliers', 'accounts_payable',
            'accounts_payable_pagamentos', 'accounts_receivable',
        ];

        $antes = [];

        foreach ($tabelas as $tabela) {
            $antes[$tabela] = $this->contabilidade($tabela);

            $this->assertGreaterThan(0, $antes[$tabela], "A demo da Daiane não criou [{$tabela}].");
        }

        (new DemoDaianeSeeder)->run();

        foreach ($tabelas as $tabela) {
            $this->assertSame(
                $antes[$tabela],
                $this->contabilidade($tabela),
                "A tabela [{$tabela}] cresceu na segunda execução da demo da Daiane.",
            );
        }
    }

    /**
     * @return array<string, int>
     */
    protected function todasAsContagens(): array
    {
        $tabelas = [
            'professionals', 'rooms', 'pacientes', 'appointments', 'odontograms',
            'odontogram_teeth', 'treatment_plans', 'treatment_plan_items', 'budgets',
            'budget_items', 'accounts_receivable', 'payments', 'prescriptions',
            'prescription_items', 'contracts', 'products', 'stock_movements',
            'audit_logs',
        ];

        $contagens = [];

        foreach ($tabelas as $tabela) {
            $contagens[$tabela] = $this->contabilidade($tabela);
        }

        return $contagens;
    }

    protected function contabilidade(string $tabela): int
    {
        return (int) DB::connection('tenant')->table($tabela)->count();
    }

    protected function clinica(): Clinic
    {
        return Clinic::findOrFail($this->clinicaId);
    }

    protected function conectar(Clinic $clinica): void
    {
        app(TenantManager::class)->set($clinica);
        app(TenantDatabaseManager::class)->connectFor($clinica->refresh());
    }
}
