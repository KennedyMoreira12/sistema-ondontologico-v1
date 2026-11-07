<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Prospect;
use App\Models\Central\ProspectImport;
use App\Models\Central\ProspectOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;
use ZipArchive;

/**
 * Importação de contatos por planilha na prospecção (exclusiva do super admin).
 *
 * O que se protege aqui: a prévia NÃO grava; a confirmação grava uma vez só e é
 * idempotente (reenviar o mesmo arquivo não duplica); telefone ausente ou
 * malformado não vira número inventado; contato que já existe é preservado; o
 * acesso é só do super admin; e tudo vive no banco central, sem tocar no banco
 * de clínica nenhuma.
 */
class PlatformProspeccaoImportacaoTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::factory()->create();
    }

    public function test_super_admin_abre_a_tela_e_ve_o_botao_na_listagem(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.importar'))
            ->assertOk()
            ->assertSee('Importar contatos por planilha');

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index'))
            ->assertOk()
            ->assertSee('Importar contatos');
    }

    public function test_suporte_e_visitante_nao_importam(): void
    {
        // Visitante (sem login) é mandado para o login.
        $this->post(route('painel.prospeccao.importar.store'))
            ->assertRedirect(route('login'));

        $suporte = AdminUser::factory()->support()->create();

        $this->actingAs($suporte, 'admin')
            ->get(route('painel.prospeccao.importar'))
            ->assertForbidden();

        $this->actingAs($suporte, 'admin')
            ->post(route('painel.prospeccao.importar.store'), ['arquivo' => $this->xlsx($this->matriz())])
            ->assertForbidden();

        $this->assertSame(0, Prospect::count());
    }

    public function test_previa_nao_grava_nada_e_classifica_as_linhas(): void
    {
        $this->enviar($this->xlsx($this->matriz()))
            ->assertRedirect(route('painel.prospeccao.importar.previa'));

        $resposta = $this->previa()
            ->assertOk()
            ->assertSee('leads-odontologicos.xlsx')
            ->assertSee('Clínica Sorriso');

        $preview = $resposta->viewData('preview');

        $this->assertSame(3, $preview['total']);
        $this->assertSame(3, $preview['imported']);
        $this->assertSame(0, $preview['duplicated']);
        $this->assertSame(1, $preview['pending']);

        // Nada foi gravado por causa da prévia.
        $this->assertSame(0, Prospect::count());
        $this->assertSame(0, ProspectImport::count());
        $this->assertSame(0, ProspectOrigin::count());
    }

    public function test_confirmar_importa_e_registra_procedencia(): void
    {
        $this->enviar($this->xlsx($this->matriz()));
        $this->confirmar()->assertRedirect(route('painel.prospeccao.index'));

        $this->assertSame(3, Prospect::count());
        $this->assertSame(3, ProspectOrigin::count());
        $this->assertDatabaseCount('prospect_interactions', 0);

        $sorriso = Prospect::query()->where('name', 'Clínica Sorriso')->firstOrFail();
        $this->assertSame('nao_contatado', $sorriso->status);
        $this->assertSame('planilha_prospeccao', $sorriso->source);
        $this->assertSame('Januária', $sorriso->city);
        $this->assertSame('MG', $sorriso->state);
        $this->assertSame('5538991639913', $sorriso->phone_normalized);
        $this->assertSame('valid', $sorriso->phone_status);
        $this->assertStringContainsString('Contato comercial listado.', (string) $sorriso->notes);
        $this->assertStringContainsString('WhatsApp: Telefone publicado', (string) $sorriso->notes);
        $this->assertStringContainsString('Fonte: https://exemplo.test/1', (string) $sorriso->notes);
        // "Não contatada" é status inicial e não vira observação.
        $this->assertStringNotContainsString('Status na planilha', (string) $sorriso->notes);

        $bem = Prospect::query()->where('name', 'Odonto Bem')->firstOrFail();
        $this->assertStringContainsString('Status na planilha: Pesquisar telefone', (string) $bem->notes);

        $mascarado = Prospect::query()->where('name', 'Sorriso Perfeito')->firstOrFail();
        $this->assertNull($mascarado->phone_normalized);
        $this->assertSame('incomplete', $mascarado->phone_status);

        $origem = ProspectOrigin::query()->where('prospect_id', $sorriso->id)->firstOrFail();
        $this->assertSame('planilha_prospeccao', $origem->source);
        $this->assertSame('leads-odontologicos.xlsx', $origem->source_file);
        $this->assertSame('Clínica Sorriso', $origem->raw['linha_original']['Clínica / profissional']);

        $import = ProspectImport::query()->latest('id')->firstOrFail();
        $this->assertSame(3, $import->imported);
        $this->assertSame($this->admin->id, $import->admin_user_id);
    }

    public function test_reimportar_o_mesmo_arquivo_nao_duplica(): void
    {
        $this->enviar($this->xlsx($this->matriz()));
        $this->confirmar();
        $this->assertSame(3, Prospect::count());

        $this->enviar($this->xlsx($this->matriz()));
        $preview = $this->previa()->viewData('preview');
        $this->assertSame(0, $preview['imported']);
        $this->assertSame(3, $preview['existentes']);

        $this->confirmar();

        $this->assertSame(3, Prospect::count());
        $this->assertSame(3, ProspectOrigin::count());

        $import = ProspectImport::query()->latest('id')->firstOrFail();
        $this->assertSame(0, $import->imported);
    }

    public function test_arquivo_sem_coluna_de_nome_e_recusado(): void
    {
        $matriz = [
            ['Cidade', 'Telefone publicado'],
            ['Januária', '(38) 99163-9913'],
        ];

        $this->enviar($this->xlsx($matriz))->assertSessionHasErrors('arquivo');

        $this->assertSame(0, Prospect::count());
    }

    public function test_extensao_invalida_e_recusada(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('leads.txt', 'Cidade;Clínica / profissional');

        $this->enviar($arquivo)->assertSessionHasErrors('arquivo');

        $this->assertSame(0, Prospect::count());
    }

    public function test_telefone_ausente_ou_malformado_nao_inventa_numero(): void
    {
        $matriz = [
            ['Clínica / profissional', 'Telefone publicado'],
            ['Sem Telefone', ''],
            ['Telefone Mascarado', '(38) 99135-****'],
        ];

        $this->enviar($this->xlsx($matriz));
        $this->confirmar();

        $sem = Prospect::query()->where('name', 'Sem Telefone')->firstOrFail();
        $this->assertNull($sem->phone_original);
        $this->assertNull($sem->phone_normalized);
        $this->assertSame('unconfirmed', $sem->phone_status);

        $mascarado = Prospect::query()->where('name', 'Telefone Mascarado')->firstOrFail();
        $this->assertSame('(38) 99135-****', $mascarado->phone_original);
        $this->assertNull($mascarado->phone_normalized);
        $this->assertSame('incomplete', $mascarado->phone_status);
    }

    public function test_contato_existente_e_preservado_e_nao_duplicado(): void
    {
        $existente = Prospect::query()->create([
            'name' => 'Clínica Sorriso',
            'name_normalized' => 'clinica sorriso',
            'phone_original' => '(38) 99163-9913',
            'phone_normalized' => '5538991639913',
            'phone_status' => 'valid',
            'city' => 'Januária',
            'state' => 'MG',
            'status' => 'negociacao',
            'notes' => 'Anotação do operador',
        ]);

        $matriz = [
            ['Cidade', 'Clínica / profissional', 'Telefone publicado', 'Status do WhatsApp', 'Status da prospecção', 'Observações'],
            ['Januária', 'Clínica Sorriso', '(38) 99163-9913', 'Telefone publicado', 'Não contatada', 'Contato comercial.'],
        ];

        $this->enviar($this->xlsx($matriz));
        $this->confirmar();

        $this->assertSame(1, Prospect::count());
        $this->assertSame(1, ProspectOrigin::count());

        $existente->refresh();
        $this->assertSame('negociacao', $existente->status);
        $this->assertSame('Anotação do operador', $existente->notes);

        $import = ProspectImport::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $import->duplicated);
        $this->assertSame(0, $import->imported);
    }

    public function test_importa_csv_com_ponto_e_virgula(): void
    {
        $this->enviar($this->csv($this->matriz()));
        $this->confirmar();

        $this->assertSame(3, Prospect::count());
    }

    public function test_importacao_usa_o_banco_central_sem_tocar_no_da_clinica(): void
    {
        $this->criarClinica();

        $this->enviar($this->xlsx($this->matriz()));
        $this->confirmar();

        $this->assertSame(3, Prospect::count());
        $this->assertFalse(Schema::connection('tenant')->hasTable('prospects'));
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function matriz(): array
    {
        return [
            ['Cidade', 'Clínica / profissional', 'Especialidade / tipo', 'Telefone publicado', 'Status do WhatsApp', 'Endereço', 'Fonte / abrir ficha', 'Status da prospecção', 'Data do contato', 'Observações'],
            ['Januária', 'Clínica Sorriso', 'Clínica odontológica', '(38) 99163-9913', 'Telefone publicado; WhatsApp a confirmar', 'Pça. Central, 9', 'https://exemplo.test/1', 'Não contatada', '', 'Contato comercial listado.'],
            ['Itacarambi', 'Odonto Bem', 'Consultório odontológico', '(38) 99947-9773', 'WhatsApp publicado no site', 'Av. Ramiro, 800', 'https://exemplo.test/2', 'Pesquisar telefone', '', 'Confirmar antes de abordar.'],
            ['Manga', 'Sorriso Perfeito', 'Clínica odontológica', '(38) 99135-****', 'Parcialmente oculto', 'Rua A, 1', 'https://exemplo.test/3', 'Referência pública', '', ''],
        ];
    }

    private function enviar(UploadedFile $arquivo): TestResponse
    {
        return $this->actingAs($this->admin, 'admin')
            ->post(route('painel.prospeccao.importar.store'), ['arquivo' => $arquivo]);
    }

    private function previa(): TestResponse
    {
        return $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.importar.previa'));
    }

    private function confirmar(): TestResponse
    {
        return $this->actingAs($this->admin, 'admin')
            ->post(route('painel.prospeccao.importar.confirmar'));
    }

    /**
     * Monta um .xlsx mínimo (zip de XMLs) com strings inline, o mesmo formato que
     * o leitor espera da planilha real.
     *
     * @param  array<int, array<int, string>>  $matriz
     */
    private function xlsx(array $matriz, string $aba = 'Leads odontológicos'): UploadedFile
    {
        $caminho = (string) tempnam(sys_get_temp_dir(), 'prosp');

        $zip = new ZipArchive;
        $zip->open($caminho, ZipArchive::OVERWRITE);

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->xml($aba).'" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
            .'Target="worksheets/sheet1.xml"/></Relationships>');

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($matriz));

        $zip->close();

        $bytes = (string) file_get_contents($caminho);
        @unlink($caminho);

        return UploadedFile::fake()->createWithContent('leads-odontologicos.xlsx', $bytes);
    }

    /**
     * @param  array<int, array<int, string>>  $matriz
     */
    private function sheetXml(array $matriz): string
    {
        $xml = '<?xml version="1.0"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($matriz as $linha => $celulas) {
            $xml .= '<row r="'.($linha + 1).'">';

            foreach ($celulas as $coluna => $valor) {
                $referencia = $this->coluna($coluna).($linha + 1);
                $xml .= '<c r="'.$referencia.'" t="inlineStr"><is><t xml:space="preserve">'
                    .$this->xml((string) $valor).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function coluna(int $indice): string
    {
        $nome = '';
        $indice++;

        while ($indice > 0) {
            $nome = chr(65 + ($indice - 1) % 26).$nome;
            $indice = intdiv($indice - 1, 26);
        }

        return $nome;
    }

    private function xml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param  array<int, array<int, string>>  $matriz
     */
    private function csv(array $matriz): UploadedFile
    {
        $linhas = array_map(fn (array $linha): string => implode(';', $linha), $matriz);

        return UploadedFile::fake()->createWithContent('leads-odontologicos.csv', implode("\n", $linhas));
    }
}
