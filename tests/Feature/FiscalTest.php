<?php

namespace Tests\Feature;

use App\Contracts\FiscalProviderInterface;
use App\Models\ClinicProfile;
use App\Models\NotaFiscal;
use App\Services\Fiscal\FiscalManager;
use App\Services\Fiscal\FiscalResultado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class FiscalTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fiscal.default' => 'mock',
            'fiscal.ambiente' => 'homologacao',
        ]);

        ClinicProfile::current()->update([
            'legal_name' => 'Clínica Teste Ltda',
            'document' => '12345678000199',
            'municipal_registration' => '123456',
            'city' => 'Belo Horizonte',
            'state' => 'MG',
            'city_ibge_code' => '3106200',
        ]);
    }

    public function test_index_lista_notas_e_atalho_para_nova_nota(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.view', 'fiscal.create');

        $this->actingAs($user)
            ->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Notas fiscais')
            ->assertSee(route('fiscal.create'), false);
    }

    public function test_tela_de_criacao_abre(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.create');

        $this->actingAs($user)
            ->get(route('fiscal.create'))
            ->assertOk()
            ->assertSee('Nova nota fiscal');
    }

    public function test_cria_rascunho_com_valores_calculados(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.create');

        $this->actingAs($user)
            ->post(route('fiscal.store'), [
                'tipo' => 'nfse',
                'valor_total' => '200.00',
                'valor_desconto' => '20.00',
                'aliquota_iss' => '5',
            ])
            ->assertRedirect();

        $nota = NotaFiscal::query()->firstOrFail();

        $this->assertSame('rascunho', $nota->status);
        $this->assertEquals(180, $nota->valor_liquido);
        $this->assertEquals(9, $nota->valor_iss);
    }

    public function test_valor_e_tipo_sao_obrigatorios(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.create');

        $this->actingAs($user)
            ->post(route('fiscal.store'), [])
            ->assertSessionHasErrors(['tipo', 'valor_total']);
    }

    public function test_emite_nota_pelo_provedor_mock(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.create', 'fiscal.emitir', 'fiscal.view');

        $nota = NotaFiscal::create([
            'tipo' => 'nfse',
            'status' => 'rascunho',
            'valor_total' => 150,
            'valor_liquido' => 150,
        ]);

        $this->actingAs($user)
            ->post(route('fiscal.emitir', $nota))
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->reconectarTenantParaAssercao();
        $nota->refresh();

        $this->assertSame('autorizada', $nota->status);
        $this->assertSame(44, strlen((string) $nota->chave));
        $this->assertNotNull($nota->emitida_em);
        $this->assertSame('mock', $nota->provider);
        $this->assertSame(1, $nota->tentativas);
    }

    public function test_nota_rejeitada_guarda_motivo_e_permite_nova_tentativa(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.create', 'fiscal.emitir');

        $this->registrarProvedorQueRejeita();

        $nota = NotaFiscal::create([
            'tipo' => 'nfse',
            'status' => 'rascunho',
            'valor_total' => 100,
            'valor_liquido' => 100,
        ]);

        $this->actingAs($user)
            ->post(route('fiscal.emitir', $nota))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->reconectarTenantParaAssercao();
        $nota->refresh();

        $this->assertSame('rejeitada', $nota->status);
        $this->assertStringContainsString('município', (string) $nota->motivo_rejeicao);
        $this->assertTrue($nota->podeEmitir());
    }

    public function test_cancela_nota_autorizada_e_exige_motivo_longo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.create', 'fiscal.emitir', 'fiscal.cancelar');

        $nota = NotaFiscal::create([
            'tipo' => 'nfse',
            'status' => 'autorizada',
            'valor_total' => 100,
            'valor_liquido' => 100,
            'chave' => str_repeat('1', 44),
            'emitida_em' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('fiscal.cancelar', $nota), ['motivo' => 'curto'])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($user)
            ->post(route('fiscal.cancelar', $nota), ['motivo' => 'Serviço cancelado antes da execução'])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->reconectarTenantParaAssercao();
        $nota->refresh();

        $this->assertSame('cancelada', $nota->status);
        $this->assertNotNull($nota->cancelada_em);
        $this->assertSame('Serviço cancelado antes da execução', $nota->motivo_cancelamento);
    }

    public function test_baixa_xml_da_nota(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.view');

        $nota = NotaFiscal::create([
            'tipo' => 'nfse',
            'status' => 'rascunho',
            'valor_total' => 50,
            'valor_liquido' => 50,
        ]);

        $this->actingAs($user)
            ->get(route('fiscal.xml', $nota))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_atualiza_rascunho_recalculando_liquido_e_iss(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.update');

        $nota = NotaFiscal::create([
            'tipo' => 'nfse',
            'status' => 'rascunho',
            'valor_total' => 100,
            'valor_liquido' => 100,
        ]);

        $this->actingAs($user)
            ->put(route('fiscal.update', $nota), [
                'tipo' => 'nfse',
                'valor_total' => '300.00',
                'valor_desconto' => '0.00',
                'aliquota_iss' => '2',
            ])
            ->assertRedirect();

        $this->reconectarTenantParaAssercao();
        $nota->refresh();

        $this->assertEquals(300, $nota->valor_liquido);
        $this->assertEquals(6, $nota->valor_iss);
    }

    public function test_nao_exclui_nota_autorizada(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('fiscal.delete');

        $nota = NotaFiscal::create([
            'tipo' => 'nfse',
            'status' => 'autorizada',
            'valor_total' => 100,
            'emitida_em' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('fiscal.destroy', $nota))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTenantDatabaseHas('notas_fiscais', ['id' => $nota->id]);
    }

    /**
     * Registra um driver que sempre recusa, para exercitar o caminho de
     * rejeição sem depender de HTTP.
     */
    private function registrarProvedorQueRejeita(): void
    {
        config([
            'fiscal.default' => 'rejeita',
            'fiscal.providers.rejeita' => [],
        ]);

        app(FiscalManager::class)->extend('rejeita', fn () => new class implements FiscalProviderInterface
        {
            public function nome(): string
            {
                return 'rejeita';
            }

            public function emitir(NotaFiscal $nota, array $payload): FiscalResultado
            {
                return new FiscalResultado(
                    sucesso: false,
                    status: 'rejeitada',
                    mensagem: 'Serviço não cadastrado no município.',
                );
            }

            public function cancelar(NotaFiscal $nota, string $motivo): FiscalResultado
            {
                return new FiscalResultado(sucesso: true, status: 'cancelada');
            }

            public function consultar(NotaFiscal $nota): FiscalResultado
            {
                return new FiscalResultado(sucesso: true, status: 'autorizada');
            }
        });
    }
}
