<?php

namespace Tests\Feature\Prospeccao;

use App\Models\Central\Prospect;
use App\Models\Central\ProspectImport;
use App\Models\Central\ProspectOrigin;
use App\Services\Platform\Prospeccao\ProspectImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProspeccaoImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function datasets(): array
    {
        $dir = database_path('prospeccao');

        return [
            json_decode((string) File::get($dir.'/odontologia_januaria.json'), true),
            json_decode((string) File::get($dir.'/saude_januaria_ampliada.json'), true),
        ];
    }

    private function importarTudo(ProspectImporter $importer): void
    {
        foreach ($this->datasets() as $source) {
            $importer->import($source);
        }
    }

    private function prospectDaOrigem(string $source, string $ref): Prospect
    {
        $origin = ProspectOrigin::query()
            ->where('source', $source)
            ->where('source_ref', $ref)
            ->firstOrFail();

        return $origin->prospect;
    }

    public function test_importar_duas_vezes_nao_duplica_nem_perde_registros(): void
    {
        $importer = app(ProspectImporter::class);

        $this->importarTudo($importer);

        $this->assertSame(110, Prospect::count(), 'odonto 49 + saúde 61 (Avelar fundido)');
        $this->assertSame(111, ProspectOrigin::count(), 'uma procedência por linha dos PDFs');

        $this->importarTudo($importer);

        $this->assertSame(110, Prospect::count(), 'reimportar não cria contatos');
        $this->assertSame(111, ProspectOrigin::count(), 'reimportar não duplica procedências');

        $reimportacao = ProspectImport::query()->latest('id')->firstOrFail();
        $this->assertSame(0, $reimportacao->imported);
        $this->assertSame(0, $reimportacao->duplicated);
    }

    public function test_funde_duplicidade_entre_os_dois_pdfs_preservando_as_duas_origens(): void
    {
        $this->importarTudo(app(ProspectImporter::class));

        $this->assertSame(1, Prospect::query()->where('phone_normalized', '5538992562222')->count());

        $avelar = Prospect::query()->where('phone_normalized', '5538992562222')->firstOrFail();

        $this->assertSame('Clínica Médica e Odontológica Avelar', $avelar->name);
        $this->assertSame(2, $avelar->origins()->count());
        $this->assertSame(
            ['odontologia_januaria', 'saude_januaria_ampliada'],
            $avelar->origins()->pluck('source')->all(),
        );
        $this->assertSame('R. Mata Machado, 16 - Centro', $avelar->address, 'merge preenche a lacuna sem apagar');
    }

    public function test_preserva_numero_original_e_procedencia(): void
    {
        $this->importarTudo(app(ProspectImporter::class));

        $prospect = $this->prospectDaOrigem('odontologia_januaria', '1');

        $this->assertSame('Clínica Odontológica Novo Sorriso - Dra. Gerusa Maria e Equipe', $prospect->name);
        $this->assertSame('(38) 99163-9913', $prospect->phone_original);
        $this->assertSame('5538991639913', $prospect->phone_normalized);
        $this->assertSame('valid', $prospect->phone_status);

        $origin = $prospect->origins()->first();
        $this->assertSame('Lista_Prospecao_LJ_Automacao_Odontologia_Januaria.pdf', $origin->source_file);
        $this->assertIsArray($origin->raw);
        $this->assertSame('1', $origin->raw['ref']);
    }

    public function test_classifica_telefones_invalidos_incompletos_e_nao_confirmados(): void
    {
        $this->importarTudo(app(ProspectImporter::class));

        $ressonar = $this->prospectDaOrigem('saude_januaria_ampliada', '21');
        $this->assertSame('invalid', $ressonar->phone_status);
        $this->assertNull($ressonar->phone_normalized);

        $caldeira = $this->prospectDaOrigem('saude_januaria_ampliada', '24');
        $this->assertSame('invalid', $caldeira->phone_status);

        $cortez = $this->prospectDaOrigem('saude_januaria_ampliada', '42');
        $this->assertSame('incomplete', $cortez->phone_status);
        $this->assertSame('553891288848', $cortez->phone_normalized);

        $plenaMente = $this->prospectDaOrigem('saude_januaria_ampliada', '4');
        $this->assertSame('unconfirmed', $plenaMente->phone_status);
        $this->assertNull($plenaMente->phone_normalized);

        $naoConfirmado = $this->prospectDaOrigem('odontologia_januaria', '40');
        $this->assertSame('unconfirmed', $naoConfirmado->phone_status);
        $this->assertNull($naoConfirmado->phone_normalized);
    }

    public function test_nao_funde_por_nome_parecido_nem_por_telefone_compartilhado(): void
    {
        $this->importarTudo(app(ProspectImporter::class));

        $compartilhado = Prospect::query()->where('phone_normalized', '553836211374')->get();
        $this->assertCount(3, $compartilhado);
        $this->assertCount(3, $compartilhado->pluck('name_normalized')->unique());

        $this->assertSame(2, Prospect::query()->where('phone_normalized', '553836215528')->count());
        $this->assertSame(2, Prospect::query()->where('phone_normalized', '553836212306')->count(), 'Cimeg x Centro Excelência, cruzando os PDFs');

        $saude = ProspectImport::query()->where('source_key', 'saude_januaria_ampliada')->firstOrFail();
        $this->assertNotEmpty($saude->report['telefones_compartilhados']);
    }

    public function test_registra_linhas_com_erro_sem_interromper_a_importacao(): void
    {
        $import = app(ProspectImporter::class)->import([
            'source_file' => 'teste_erros.pdf',
            'source_key' => 'teste_erros',
            'default_city' => 'Januária',
            'default_state' => 'MG',
            'rows' => [
                ['ref' => '1', 'name' => '', 'phone_original' => '(38) 99999-9999', 'priority' => 'Alta'],
                ['ref' => '2', 'name' => 'Contato Bom', 'phone_original' => '(38) 98888-8888', 'priority' => 'Alta'],
            ],
        ]);

        $this->assertSame(2, $import->total);
        $this->assertSame(1, $import->imported);
        $this->assertSame(1, $import->rejected);
        $this->assertSame(1, Prospect::count());
        $this->assertCount(1, $import->report['rejeitados']);
        $this->assertSame('registro sem nome', $import->report['rejeitados'][0]['motivo']);
    }

    public function test_reimportacao_nao_sobrescreve_alteracoes_do_operador(): void
    {
        $importer = app(ProspectImporter::class);
        $this->importarTudo($importer);

        $prospect = $this->prospectDaOrigem('odontologia_januaria', '1');
        $prospect->update(['status' => 'negociacao', 'notes' => 'contato feito pelo telefone']);
        $prospect->forceFill(['phone_original' => '(38) 90000-0000'])->save();

        $totalAntes = Prospect::count();

        $this->importarTudo($importer);

        $prospect->refresh();
        $this->assertSame('negociacao', $prospect->status);
        $this->assertSame('contato feito pelo telefone', $prospect->notes);
        $this->assertSame('(38) 90000-0000', $prospect->phone_original);
        $this->assertSame('5538991639913', $prospect->phone_normalized);
        $this->assertSame($totalAntes, Prospect::count());
    }

    public function test_comando_importa_as_duas_listas(): void
    {
        $this->artisan('prospeccao:importar', ['--dir' => database_path('prospeccao')])
            ->assertExitCode(0);

        $this->assertSame(110, Prospect::count());
        $this->assertSame(2, ProspectImport::count());
    }
}
