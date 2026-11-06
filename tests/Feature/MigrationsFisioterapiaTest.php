<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Ciclo de subida e descida das migrations da Fase C.
 *
 * As migrations de clínica são ADITIVAS: acrescentam tabela e coluna, sem
 * renomear nem remover o que a odontologia usa. Isso só vale alguma coisa se
 * a descida funcionar — porque é a descida que garante que uma Clínica de
 * fisioterapia possa ser desfeita sem deixar trash no banco.
 *
 * O teste roda as migrations num banco descartável, desfaz e refaz, e confere
 * que o que existia antes continua existindo depois.
 */
class MigrationsFisioterapiaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tabelas que a Fase C acrescenta no banco da clínica.
     *
     * @var list<string>
     */
    protected const TABELAS_NOVAS = [
        'avaliacoes',
        'evolucoes_sessao',
        'pacotes_sessao',
        'consumo_sessao',
    ];

    protected string $scratchPath;

    protected array $configAnterior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratchPath = storage_path('framework/testing/migrations_'.uniqid().'.sqlite');
        touch($this->scratchPath);

        $this->configAnterior = config('database.connections.tenant');

        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->scratchPath,
            'prefix' => '',
            'prefix_indexes' => true,
            'foreign_key_constraints' => true,
        ]);

        DB::purge('tenant');
    }

    protected function tearDown(): void
    {
        DB::purge('tenant');
        Config::set('database.connections.tenant', $this->configAnterior);

        if (is_file($this->scratchPath)) {
            @unlink($this->scratchPath);
        }

        parent::tearDown();
    }

    public function test_a_subida_cria_o_novo_e_preserva_o_antigo(): void
    {
        $this->rodarMigrationsTenant();

        foreach (self::TABELAS_NOVAS as $tabela) {
            $this->assertTrue(
                Schema::connection('tenant')->hasTable($tabela),
                "A tabela [{$tabela}] tem de existir depois da subida."
            );
        }

        $this->assertTrue(Schema::connection('tenant')->hasColumn('professionals', 'registro_profissional'));
        $this->assertTrue(Schema::connection('tenant')->hasColumn('professionals', 'conselho_regional'));

        // O que a odontologia usa continua lá, com o mesmo nome.
        $this->assertTrue(Schema::connection('tenant')->hasColumn('professionals', 'cro'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('odontograms'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('tooth_conditions'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('treatment_plans'));
    }

    public function test_a_descida_tira_o_novo_e_devolve_o_antigo(): void
    {
        $this->rodarMigrationsTenant();

        $this->rodarMigrationsTenant('migrate:rollback', ['--step' => $this->passosParaDesfazerFaseC()]);

        foreach (self::TABELAS_NOVAS as $tabela) {
            $this->assertFalse(
                Schema::connection('tenant')->hasTable($tabela),
                "A tabela [{$tabela}] tem de sumir depois da descida."
            );
        }

        $this->assertFalse(Schema::connection('tenant')->hasColumn('professionals', 'registro_profissional'));

        // O que a odontologia usa continua lá, com o mesmo nome.
        $this->assertTrue(Schema::connection('tenant')->hasColumn('professionals', 'cro'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('odontograms'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('tooth_conditions'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('treatment_plans'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('pacientes'));
    }

    public function test_refazer_a_subida_depois_da_descida_deixa_o_banco_no_ponto(): void
    {
        $this->rodarMigrationsTenant();
        $this->rodarMigrationsTenant('migrate:rollback', ['--step' => $this->passosParaDesfazerFaseC()]);
        $this->rodarMigrationsTenant();

        foreach (self::TABELAS_NOVAS as $tabela) {
            $this->assertTrue(Schema::connection('tenant')->hasTable($tabela));
        }

        $this->assertTrue(Schema::connection('tenant')->hasColumn('professionals', 'registro_profissional'));
        $this->assertTrue(Schema::connection('tenant')->hasColumn('professionals', 'cro'));
        $this->assertTrue(Schema::connection('tenant')->hasTable('odontograms'));
    }

    public function test_a_migration_da_vertical_no_painel_da_empresa_tem_descida(): void
    {
        $migration = require database_path('migrations/central/2026_10_21_000001_add_vertical_to_clinics_table.php');

        $this->assertTrue(Schema::hasColumn('clinics', 'vertical'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('clinics', 'vertical'));
        $this->assertTrue(Schema::hasColumn('clinics', 'status'), 'A coluna do estado não pode sumir com a vertical.');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('clinics', 'vertical'));
    }

    /**
     * Quantas migrations precisam ser desfeitas para chegar ao ponto anterior
     * à Fase C.
     *
     * O número é calculado pela ordem real das migrations já executadas, e não
     * fixado em código: migrations novas (como as do financeiro) não podem
     * fazer este teste deixar de exercitar a descida da fisioterapia.
     */
    private function passosParaDesfazerFaseC(): int
    {
        $migracoes = DB::connection('tenant')
            ->table('migrations')
            ->orderByDesc('id')
            ->pluck('migration');

        $posicao = $migracoes->search(
            fn (string $nome): bool => str_contains($nome, '2026_10_21_000002_add_registro_profissional')
        );

        $this->assertNotFalse($posicao, 'A migration inicial da Fase C precisa estar registrada.');

        return $posicao + 1;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function rodarMigrationsTenant(string $comando = 'migrate', array $extra = []): void
    {
        $saida = new BufferedOutput;

        $codigo = Artisan::call($comando, array_merge([
            '--database' => 'tenant',
            '--path' => [database_path('migrations/tenant')],
            '--realpath' => true,
            '--force' => true,
        ], $extra), $saida);

        if ($codigo !== 0) {
            throw new RuntimeException("A migration [{$comando}] falhou (exit {$codigo}):\n".$saida->fetch());
        }
    }
}
