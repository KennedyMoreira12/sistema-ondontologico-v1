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

    /**
     * Arquivos de migration que a Fase C acrescenta ao banco da clinica.
     *
     * O rollback tem de ser feito POR ARQUIVO (`--path`), e nao por `--step`:
     * entre o bloco de fisioterapia e o topo da fila existem outras migrations
     * (fiscal, WhatsApp, indice) que sao revertidas antes por ordem de nome.
     * Reverter por contagem levaria junto o que nao e da fisioterapia.
     *
     * @var list<string>
     */
    protected const MIGRACOES_FISIOTERAPIA = [
        '2026_10_21_000002_add_registro_profissional_to_professionals_table.php',
        '2026_10_21_000003_create_avaliacoes_table.php',
        '2026_10_21_000004_create_evolucoes_sessao_table.php',
        '2026_10_21_000005_create_pacotes_sessao_table.php',
        '2026_10_21_000006_create_consumo_sessao_table.php',
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

        $this->rodarMigrationsTenant('migrate:rollback', ['--path' => $this->caminhosMigrationsFisioterapia()]);

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
        $this->rodarMigrationsTenant('migrate:rollback', ['--path' => $this->caminhosMigrationsFisioterapia()]);
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
     * Caminhos absolutos apenas das migrations de fisioterapia.
     *
     * @return list<string>
     */
    protected function caminhosMigrationsFisioterapia(): array
    {
        return array_map(
            fn (string $arquivo): string => database_path('migrations/tenant/'.$arquivo),
            self::MIGRACOES_FISIOTERAPIA
        );
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
