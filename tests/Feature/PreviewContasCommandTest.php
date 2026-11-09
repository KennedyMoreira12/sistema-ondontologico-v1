<?php

namespace Tests\Feature;

use App\Models\AccountsPayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Prévia do CONTAS.xlsx.
 *
 * O arquivo real não fica no repositório, então o teste monta um .xlsx mínimo
 * com a mesma estrutura (sharedStrings + workbook + worksheet) e prova que o
 * comando lê os números, identifica juros e duplicidade — e, principalmente,
 * que não grava nada no banco.
 */
class PreviewContasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_le_xlsx_e_nao_grava_no_banco(): void
    {
        $caminho = $this->criarXlsxDeTeste();
        $antes = AccountsPayable::count();

        $this->artisan('financeiro:preview-contas', ['arquivo' => $caminho])
            ->expectsOutputToContain('somente leitura')
            ->expectsOutputToContain('JANEIRO 2026')
            ->expectsOutputToContain('R$ 3.300,00')
            ->expectsOutputToContain('R$ 20,00')
            ->expectsOutputToContain('Nenhum dado foi gravado')
            ->assertExitCode(0);

        $this->assertSame($antes, AccountsPayable::count());

        @unlink($caminho);
    }

    public function test_preview_recusa_arquivo_inexistente(): void
    {
        $this->artisan('financeiro:preview-contas', ['arquivo' => 'nao-existe.xlsx'])
            ->expectsOutputToContain('Arquivo não encontrado')
            ->assertExitCode(1);
    }

    /**
     * Gera um .xlsx com uma aba, cabeçalho e três linhas de dados, sendo uma
     * duplicada da outra. Datas vêm como texto para não depender do serial.
     */
    private function criarXlsxDeTeste(): string
    {
        $caminho = sys_get_temp_dir().DIRECTORY_SEPARATOR.'contas-preview-'.uniqid().'.xlsx';

        $zip = new ZipArchive;
        $zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('xl/sharedStrings.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="11" uniqueCount="11">
    <si><t>TIPO</t></si>
    <si><t>FAVORECIDO</t></si>
    <si><t>VENCIMENTO</t></si>
    <si><t>VALOR</t></si>
    <si><t>JUROS</t></si>
    <si><t>Aluguel</t></si>
    <si><t>Imobiliaria Centro</t></si>
    <si><t>10/01/2026</t></si>
    <si><t>Servico</t></si>
    <si><t>Fulano</t></si>
    <si><t>10/02/2026</t></si>
</sst>
XML);

        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="JANEIRO 2026" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML);

        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);

        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1">
            <c r="A1" t="s"><v>0</v></c>
            <c r="B1" t="s"><v>1</v></c>
            <c r="C1" t="s"><v>2</v></c>
            <c r="D1" t="s"><v>3</v></c>
            <c r="E1" t="s"><v>4</v></c>
        </row>
        <row r="2">
            <c r="A2" t="s"><v>5</v></c>
            <c r="B2" t="s"><v>6</v></c>
            <c r="C2" t="s"><v>7</v></c>
            <c r="D2"><v>1500</v></c>
            <c r="E2"><v>0</v></c>
        </row>
        <row r="3">
            <c r="A3" t="s"><v>5</v></c>
            <c r="B3" t="s"><v>6</v></c>
            <c r="C3" t="s"><v>7</v></c>
            <c r="D3"><v>1500</v></c>
            <c r="E3"><v>0</v></c>
        </row>
        <row r="4">
            <c r="A4" t="s"><v>8</v></c>
            <c r="B4" t="s"><v>9</v></c>
            <c r="C4" t="s"><v>10</v></c>
            <c r="D4"><v>300</v></c>
            <c r="E4"><v>20</v></c>
        </row>
    </sheetData>
</worksheet>
XML);

        $zip->close();

        return $caminho;
    }
}
