<?php

namespace Tests\Feature\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Prospect;
use App\Models\Central\ProspectOrigin;
use App\Services\Platform\Prospeccao\ProspectImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Aba de prospecção comercial no painel (exclusiva do super admin).
 *
 * O que se protege aqui: ninguém além do super admin entra; a listagem e os
 * indicadores são consultas filtradas de verdade (não valores fixos); a etapa do
 * funil é validada contra a config e cada mudança vira histórico; e a base
 * importada (110 contatos / 111 procedências) permanece íntegra.
 */
class PlatformProspeccaoTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AdminUser::factory()->create();
    }

    public function test_super_admin_acessa_a_listagem(): void
    {
        $this->prospecto(['name' => 'Clínica Alfa Odontologia']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index'))
            ->assertOk()
            ->assertSee('Prospecção comercial')
            ->assertSee('Clínica Alfa Odontologia');
    }

    public function test_suporte_nao_acessa_nem_altera_prospeccao(): void
    {
        $suporte = AdminUser::factory()->support()->create();
        $prospect = $this->prospecto(['status' => 'nao_contatado']);

        $this->actingAs($suporte, 'admin')->get(route('painel.prospeccao.index'))->assertForbidden();
        $this->actingAs($suporte, 'admin')->get(route('painel.prospeccao.show', $prospect))->assertForbidden();
        $this->actingAs($suporte, 'admin')
            ->patch(route('painel.prospeccao.update', $prospect), ['status' => 'negociacao'])
            ->assertForbidden();

        $this->assertSame('nao_contatado', $prospect->fresh()->status);
    }

    public function test_usuario_de_clinica_e_visitante_nao_acessam(): void
    {
        ['user' => $usuario] = $this->criarClinica();

        $this->get(route('painel.prospeccao.index'))->assertRedirect(route('login'));

        $this->actingAs($usuario, 'web')
            ->get(route('painel.prospeccao.index'))
            ->assertRedirect(route('login'));
    }

    public function test_busca_por_nome_telefone_e_endereco(): void
    {
        $this->prospecto(['name' => 'Clínica Alfa', 'address' => 'Rua das Flores, 10']);
        $this->prospecto([
            'name' => 'Clínica Beta',
            'phone_original' => '(38) 88888-8888',
            'phone_normalized' => '5538888888888',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index', ['busca' => 'Alfa']))
            ->assertSee('Clínica Alfa')
            ->assertDontSee('Clínica Beta');

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index', ['busca' => '88888']))
            ->assertSee('Clínica Beta')
            ->assertDontSee('Clínica Alfa');

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index', ['busca' => 'Flores']))
            ->assertSee('Clínica Alfa')
            ->assertDontSee('Clínica Beta');
    }

    public function test_filtros_e_resumo_refletem_o_recorte(): void
    {
        $this->prospecto(['name' => 'Odonto Um', 'category' => 'Odontologia', 'status' => 'nao_contatado']);
        $this->prospecto(['name' => 'Odonto Dois', 'category' => 'Odontologia', 'status' => 'cliente']);
        $this->prospecto(['name' => 'Fisio Um', 'category' => 'Fisioterapia', 'status' => 'negociacao']);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index', ['category' => 'Odontologia']))
            ->assertOk();

        $nomes = $response->viewData('prospects')->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Odonto Um', 'Odonto Dois'], $nomes);

        $resumo = $response->viewData('resumo');
        $this->assertSame(2, $resumo['total']);
        $this->assertSame(1, $resumo['nao_contatado']);
        $this->assertSame(1, $resumo['cliente']);
        $this->assertSame(0, $resumo['negociacao']);

        $porEtapa = $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index', ['status' => 'nao_contatado']))
            ->viewData('prospects');

        $this->assertSame(['Odonto Um'], $porEtapa->pluck('name')->all());
    }

    public function test_resumo_tem_indicadores_reais(): void
    {
        $this->prospecto(['status' => 'negociacao']);
        $this->prospecto(['status' => 'reuniao_marcada']);
        $this->prospecto(['status' => 'cliente']);
        $this->prospecto(['status' => 'nao_contatado', 'phone_status' => 'unconfirmed', 'phone_normalized' => null]);

        $resumo = $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index'))
            ->viewData('resumo');

        $this->assertSame(4, $resumo['total']);
        $this->assertSame(1, $resumo['a_validar']);
        $this->assertSame(1, $resumo['nao_contatado']);
        $this->assertSame(1, $resumo['negociacao']);
        $this->assertSame(1, $resumo['reuniao_marcada']);
        $this->assertSame(1, $resumo['cliente']);
    }

    public function test_listagem_e_paginada(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->prospecto(['name' => 'Contato '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $primeira = $this->actingAs($this->admin, 'admin')->get(route('painel.prospeccao.index'));
        $this->assertCount(25, $primeira->viewData('prospects'));

        $segunda = $this->actingAs($this->admin, 'admin')->get(route('painel.prospeccao.index', ['page' => 2]));
        $this->assertCount(5, $segunda->viewData('prospects'));
    }

    public function test_indicadores_visuais_de_validacao_e_telefone_compartilhado(): void
    {
        $this->prospecto(['name' => 'Contato A', 'phone_normalized' => '553836211374', 'phone_status' => 'valid']);
        $this->prospecto(['name' => 'Contato B', 'phone_normalized' => '553836211374', 'phone_status' => 'valid']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.index'))
            ->assertOk()
            ->assertSee('compartilhado');
    }

    public function test_atualiza_etapa_registrando_historico_e_auditoria(): void
    {
        $prospect = $this->prospecto(['status' => 'nao_contatado', 'notes' => null]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('painel.prospeccao.update', $prospect), [
                'status' => 'negociacao',
                'priority' => 'alta',
                'channel' => 'telefone',
                'result' => 'pediu proposta',
                'notes' => 'Falei com a recepção',
                'next_follow_up_at' => '2026-11-01',
            ])
            ->assertRedirect(route('painel.prospeccao.show', $prospect));

        $prospect->refresh();
        $this->assertSame('negociacao', $prospect->status);
        $this->assertSame('Falei com a recepção', $prospect->notes);
        $this->assertSame('2026-11-01', $prospect->next_follow_up_at->format('Y-m-d'));

        $this->assertDatabaseHas('prospect_interactions', [
            'prospect_id' => $prospect->id,
            'admin_user_id' => $this->admin->id,
            'status_before' => 'nao_contatado',
            'status_after' => 'negociacao',
            'result' => 'pediu proposta',
        ]);

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'prospeccao.oportunidade_atualizada',
            'entity' => 'prospect',
            'entity_id' => $prospect->id,
        ]);
    }

    public function test_rejeita_etapa_fora_do_funil(): void
    {
        $prospect = $this->prospecto(['status' => 'nao_contatado']);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('painel.prospeccao.update', $prospect), ['status' => 'etapa_inexistente'])
            ->assertSessionHasErrors('status');

        $this->assertSame('nao_contatado', $prospect->fresh()->status);
        $this->assertDatabaseCount('prospect_interactions', 0);
    }

    public function test_marcar_sem_interesse_exige_motivo(): void
    {
        $prospect = $this->prospecto(['status' => 'nao_contatado']);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('painel.prospeccao.update', $prospect), ['status' => 'sem_interesse'])
            ->assertSessionHasErrors('disinterest_reason');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('painel.prospeccao.update', $prospect), [
                'status' => 'sem_interesse',
                'disinterest_reason' => 'Já usa outro sistema',
            ])
            ->assertRedirect();

        $this->assertSame('Já usa outro sistema', $prospect->fresh()->disinterest_reason);
    }

    public function test_validacao_manual_marca_data(): void
    {
        $prospect = $this->prospecto(['phone_status' => 'unconfirmed', 'phone_normalized' => null, 'validated_at' => null]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('painel.prospeccao.update', $prospect), ['status' => 'nao_contatado', 'validated' => 1])
            ->assertRedirect();

        $this->assertNotNull($prospect->fresh()->validated_at);
    }

    public function test_nao_altera_dados_de_outros_modulos(): void
    {
        ['clinic' => $clinic] = $this->criarClinica();
        $prospect = $this->prospecto(['status' => 'nao_contatado']);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('painel.prospeccao.update', $prospect), [
                'status' => 'cliente',
                'clinic_id' => $clinic->id,
                'import_id' => 999,
                'converted_clinic_id' => $clinic->id,
            ])
            ->assertRedirect();

        $prospect->refresh();
        $this->assertNull($prospect->import_id);
        $this->assertNull($prospect->converted_clinic_id);
        $this->assertSame('Clínica Teste', $clinic->fresh()->name);
    }

    public function test_base_importada_permanece_integra(): void
    {
        $dir = database_path('prospeccao');

        if (! is_file($dir.'/odontologia_januaria.json') || ! is_file($dir.'/saude_januaria_ampliada.json')) {
            $this->markTestSkipped('Datasets de prospecção ausentes no ambiente.');
        }

        $importer = app(ProspectImporter::class);

        foreach (['odontologia_januaria', 'saude_januaria_ampliada'] as $arquivo) {
            $importer->import(json_decode((string) File::get($dir.'/'.$arquivo.'.json'), true));
        }

        $this->assertSame(110, Prospect::count());
        $this->assertSame(111, ProspectOrigin::count());
    }

    public function test_download_do_pdf_de_origem(): void
    {
        $this->actingAs(AdminUser::factory()->support()->create(), 'admin')
            ->get(route('painel.prospeccao.origem', 'odontologia_januaria'))
            ->assertForbidden();

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.origem', 'fonte_desconhecida'))
            ->assertNotFound();

        $this->actingAs($this->admin, 'admin')
            ->get(route('painel.prospeccao.origem', 'odontologia_januaria'))
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function prospecto(array $atributos = []): Prospect
    {
        static $sequencia = 0;
        $sequencia++;

        return Prospect::query()->create(array_merge([
            'name' => 'Clínica Teste '.$sequencia,
            'name_normalized' => 'clinica teste '.$sequencia,
            'phone_original' => '(38) 99999-0000',
            'phone_normalized' => '5538999990000',
            'phone_status' => 'valid',
            'category' => 'Odontologia',
            'city' => 'Januária',
            'state' => 'MG',
            'priority' => 'alta',
            'status' => 'nao_contatado',
        ], $atributos));
    }
}
