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
 * Como o sistema é vertical-agnóstico (atende clínicas de qualquer ramo),
 * todas as tabelas estão disponíveis para qualquer clínica. Estes testes
 * existem para garantir que nenhuma tabela seja removida ou classificada
 * incorretamente.
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

    public function test_todas_as_tabelas_especificas_continuam_existindo(): void
    {
        $clinica = $this->criarClinica('Clínica Qualquer');

        $this->conectar($clinica['clinic']);

        foreach (InventarioTabelas::TABELAS_ESPECIFICAS as $tabela) {
            $this->assertTrue(
                Schema::connection('tenant')->hasTable($tabela),
                "A tabela [{$tabela}] não pode ser removida."
            );
        }
    }

    public function test_qualquer_clinica_tem_o_mesmo_conjunto_de_tabelas(): void
    {
        $clinicaA = $this->criarClinica('Clínica A');
        $clinicaB = $this->criarClinica('Clínica B');
        $clinicaB['clinic']->update(['vertical' => 'qualquer']);

        $this->conectar($clinicaA['clinic']);
        $tabelasA = app(InventarioTabelas::class)->tabelasExistentes();

        $this->conectar($clinicaB['clinic']);
        $tabelasB = app(InventarioTabelas::class)->tabelasExistentes();

        // O banco é o mesmo para qualquer clínica; a vertical não restringe tabelas.
        $this->assertSame($tabelasA, $tabelasB);
    }

    public function test_o_cro_e_o_registro_profissional_estao_presentes(): void
    {
        $this->criarClinica('Clínica Profissionais');

        $this->assertTrue(
            Schema::connection('tenant')->hasColumn('professionals', 'cro'),
            'A coluna CRO precisa existir.'
        );

        $this->assertTrue(
            Schema::connection('tenant')->hasColumn('professionals', 'registro_profissional'),
            'O registro no conselho precisa existir ao lado do CRO.'
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
        $clinica['clinic']->update(['vertical' => 'qualquer']);

        $this->artisan('tenants:auditar-tabelas', ['--clinica' => $clinica['clinic']->id])
            ->assertSuccessful();
    }
}
