<?php

namespace Tests\Feature;

use App\Models\Procedure;
use App\Services\Tenant\InventarioTabelas;
use App\Services\Tenant\VerticalManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Auditoria das tabelas do banco da clínica.
 *
 * A promessa da Fase C é que adaptar o sistema para a fisioterapia NÃO desfaça
 * a odontologia: as tabelas de odontologia continuam lá, o CRO continua
 * existindo e o catálogo é de cada clínica. Estes testes existem para que essa
 * promessa seja quebrada em vermelho se alguém mexer nela.
 */
class AuditoriaTabelasTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_toda_tabela_do_banco_da_clinica_esta_classificada(): void
    {
        $this->criarClinica('Clínica Auditoria');

        $resultado = app(InventarioTabelas::class)->auditar();

        $this->assertTrue(
            $resultado['ok'],
            'Tabelas sem classificação ou ausentes: '.implode(' | ', $resultado['problemas'])
        );
    }

    public function test_tabelas_odontologicas_continuam_existindo_para_clinica_de_fisioterapia(): void
    {
        $clinica = $this->criarClinica('Clínica de Fisioterapia');
        $clinica['clinic']->update(['vertical' => VerticalManager::FISIOTERAPIA]);

        $this->conectar($clinica['clinic']);

        foreach (InventarioTabelas::TABELAS_ODONTOLOGIA as $tabela) {
            $this->assertTrue(
                Schema::connection('tenant')->hasTable($tabela),
                "A tabela odontológica [{$tabela}] não pode ser removida."
            );
        }
    }

    public function test_as_duas_verticais_tem_o_mesmo_conjunto_de_tabelas(): void
    {
        $odontologica = $this->criarClinica('Clínica Odontológica');
        $fisioterapia = $this->criarClinica('Clínica de Fisioterapia');
        $fisioterapia['clinic']->update(['vertical' => VerticalManager::FISIOTERAPIA]);

        $this->conectar($odontologica['clinic']);
        $tabelasOdontologia = app(InventarioTabelas::class)->tabelasExistentes();

        $this->conectar($fisioterapia['clinic']);
        $tabelasFisioterapia = app(InventarioTabelas::class)->tabelasExistentes();

        // A vertical decide o que a clínica ENXERGA, não o que o banco tem.
        $this->assertSame($tabelasOdontologia, $tabelasFisioterapia);
    }

    public function test_o_cro_foi_preservado_e_o_registro_profissional_acrescentado(): void
    {
        $this->criarClinica('Clínica Profissionais');

        $this->assertTrue(
            Schema::connection('tenant')->hasColumn('professionals', 'cro'),
            'A coluna CRO não pode ser removida: a clínica odontológica continua usando.'
        );

        $this->assertTrue(
            Schema::connection('tenant')->hasColumn('professionals', 'registro_profissional'),
            'O registro no conselho (CREFITO) precisa existir ao lado do CRO.'
        );
    }

    public function test_o_catalogo_e_de_cada_clinica(): void
    {
        $primeira = $this->criarClinica('Clínica do Catálogo');
        $segunda = $this->criarClinica('Outra Clínica do Catálogo');

        $this->conectar($primeira['clinic']);

        Procedure::create(['codigo' => 'FIS-001', 'nome' => 'Avaliação inicial']);

        $this->conectar($segunda['clinic']);

        $this->assertSame(0, Procedure::count(), 'O catálogo de uma clínica não pode vazar para a outra.');
    }

    public function test_o_comando_de_auditoria_passa(): void
    {
        $clinica = $this->criarClinica('Clínica do Comando');
        $clinica['clinic']->update(['vertical' => VerticalManager::FISIOTERAPIA]);

        $this->artisan('tenants:auditar-tabelas', ['--clinica' => $clinica['clinic']->id])
            ->assertSuccessful();
    }
}
