<?php

namespace Tests\Feature\Fiscal;

use App\Models\FiscalCompany;
use App\Models\Nfse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class FiscalControllerTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    protected ?object $clinica = null;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('fiscal.sendnf.base_url', 'https://api.sendnf.test');
        Config::set('fiscal.sendnf.api_key', 'nt_test_abc123');
        Config::set('fiscal.sendnf.enabled', false);
    }

    protected function tearDown(): void
    {
        Http::preventStrayRequests();
        parent::tearDown();
    }

    protected function usuarioCom(array $permissoes): array
    {
        [$clinic, $user] = $this->criarUsuarioComPermissao(...$permissoes);

        $this->clinica = $clinic;

        return [$clinic, $user];
    }

    /**
     * Garante uma empresa fiscal vinculada à clínica do teste (tenant atual).
     */
    protected function empresaDeTeste(): FiscalCompany
    {
        return FiscalCompany::updateOrCreate(
            ['external_id' => (string) $this->clinica->id],
            [
                'cnpj' => '11222333000181',
                'sendnf_company_id' => 'cmp_teste',
                'status' => 'ready',
                'enabled' => true,
            ]
        );
    }

    /**
     * Cria um rascunho de NFS-e já vinculado à empresa fiscal da clínica de
     * teste, respeitando a NOT NULL de fiscal_company_id.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function novaNfse(object $user, array $attrs = []): Nfse
    {
        $empresa = $this->empresaDeTeste();

        return Nfse::create(array_merge([
            'fiscal_company_id' => $empresa->id,
            'idempotency_key' => $attrs['idempotency_key'] ?? 'idem_'.uniqid(),
            'status' => 'draft',
            'issued_by_user_id' => $user->id,
        ], $attrs));
    }

    public function test_index_requer_permissao_fiscal_view(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view']);

        $this->actingAs($user)
            ->get(route('admin.fiscal.index'))
            ->assertForbidden();
    }

    public function test_index_aca_para_usuario_com_fiscal_view(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        $this->actingAs($user)
            ->get(route('admin.fiscal.index'))
            ->assertOk()
            ->assertSee('Fiscal - NFS-e');
    }

    public function test_update_config_requer_fiscal_update(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        $this->actingAs($user)
            ->put(route('admin.fiscal.config.update'), ['enabled' => true])
            ->assertForbidden();
    }

    public function test_update_config_habilita_opcional_localmente(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.update']);

        $this->actingAs($user)
            ->put(route('admin.fiscal.config.update'), ['enabled' => true])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->reconectarTenantParaAssercao();
        $this->assertTenantDatabaseHas('fiscal_companies', ['enabled' => true]);
    }

    public function test_register_requer_fiscal_update(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        $this->actingAs($user)
            ->post(route('admin.fiscal.register'), ['cnpj' => '11.222.333/0001-81'])
            ->assertForbidden();
    }

    public function test_register_cadastra_empresa_na_sendnf(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.update']);

        Http::fake([
            'api.sendnf.test/v1/companies' => Http::response([
                'id' => 'cmp_reg',
                'external_id' => (string) $clinic->id,
                'cnpj' => '11222333000181',
                'razao_social' => 'Clinica Exemplo Ltda',
                'state' => 'ready',
            ], 201),
        ]);

        $this->actingAs($user)
            ->post(route('admin.fiscal.register'), ['cnpj' => '11.222.333/0001-81'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->reconectarTenantParaAssercao();
        $this->assertTenantDatabaseHas('fiscal_companies', [
            'external_id' => (string) $clinic->id,
            'sendnf_company_id' => 'cmp_reg',
        ]);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/v1/companies')
                && $request->hasHeader('Authorization');
        });
    }

    public function test_readiness_consulta_api_e_atualiza_status(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        FiscalCompany::updateOrCreate(
            ['external_id' => (string) $clinic->id],
            ['sendnf_company_id' => 'cmp_rdy', 'status' => 'not_configured']
        );

        Http::fake([
            'api.sendnf.test/v1/companies/cmp_rdy/readiness' => Http::response([
                'state' => 'ready',
                'nfse_ready' => true,
                'missing' => [],
            ], 200),
        ]);

        $this->actingAs($user)
            ->post(route('admin.fiscal.readiness'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->reconectarTenantParaAssercao();
        $this->assertTenantDatabaseHas('fiscal_companies', [
            'external_id' => (string) $clinic->id,
            'status' => 'ready',
        ]);
    }

    public function test_nfse_index_lista_rascunhos(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        $this->actingAs($user)
            ->get(route('admin.fiscal.nfse.index'))
            ->assertOk()
            ->assertSee('Notas fiscais (NFS-e)');
    }

    public function test_nfse_show_detalhe_da_nota(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        $nfse = $this->novaNfse($user, [
            'tomador_nome' => 'Maria Silva',
            'tomador_documento' => '529.982.247-25',
            'servico_descricao' => 'Consulta',
            'valor_servico' => 150.00,
        ]);

        $this->actingAs($user)
            ->get(route('admin.fiscal.nfse.show', $nfse))
            ->assertOk()
            ->assertSee('Nota fiscal #'.$nfse->id)
            ->assertSee('Maria Silva');
    }

    public function test_preview_requer_fiscal_update_e_consulta_sem_transmitir(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        $nfse = $this->novaNfse($user, [
            'idempotency_key' => 'idem-prev-1',
            'tomador_nome' => 'Maria Silva',
            'servico_descricao' => 'Consulta',
            'valor_servico' => 150.00,
        ]);

        $this->actingAs($user)
            ->post(route('admin.fiscal.nfse.preview', $nfse), [
                'tomador_documento' => '529.982.247-25',
                'tomador_nome' => 'Maria Silva',
                'servico_descricao' => 'Consulta',
                'valor_servico_cents' => 15000,
            ])
            ->assertForbidden();
    }

    public function test_preview_consulta_sendnf_sem_transmitir(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.update']);

        $nfse = $this->novaNfse($user, [
            'idempotency_key' => 'idem-prev-2',
            'tomador_nome' => 'Maria Silva',
            'servico_descricao' => 'Consulta',
            'valor_servico' => 150.00,
        ]);

        Http::fake([
            'api.sendnf.test/v1/nfse/preview' => Http::response([
                'id' => 'prev_x',
                'status' => 'pending',
                'warnings' => [],
            ], 200),
        ]);

        $this->actingAs($user)
            ->post(route('admin.fiscal.nfse.preview', $nfse), [
                'tomador_documento' => '529.982.247-25',
                'tomador_nome' => 'Maria Silva',
                'servico_descricao' => 'Consulta',
                'valor_servico_cents' => 15000,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/v1/nfse/preview')
                && $request->hasHeader('Idempotency-Key');
        });
    }

    public function test_emit_bloqueado_mesmo_com_permissao_quando_integracao_desabilitada(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.emit']);

        $nfse = $this->novaNfse($user, [
            'idempotency_key' => 'idem-emit-1',
        ]);

        $this->actingAs($user)
            ->post(route('admin.fiscal.nfse.emit', $nfse))
            ->assertStatus(403);
    }

    public function test_cancel_bloqueado_mesmo_com_permissao_quando_integracao_desabilitada(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.cancel']);

        $nfse = $this->novaNfse($user, [
            'idempotency_key' => 'idem-cancel-1',
        ]);

        $this->actingAs($user)
            ->post(route('admin.fiscal.nfse.cancel', $nfse))
            ->assertStatus(403);
    }

    public function test_pdf_retorna_conteudo_quando_nota_transmitida(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        $nfse = $this->novaNfse($user, [
            'idempotency_key' => 'idem-pdf-1',
            'status' => 'authorized',
            'sendnf_id' => 'doc_pdf_1',
        ]);

        Http::fake([
            'api.sendnf.test/v1/documents/doc_pdf_1/pdf' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->actingAs($user)
            ->get(route('admin.fiscal.nfse.pdf', $nfse))
            ->assertOk()
            ->assertSee('%PDF');
    }

    public function test_xml_retorna_conteudo_quando_nota_transmitida(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        $nfse = $this->novaNfse($user, [
            'idempotency_key' => 'idem-xml-1',
            'status' => 'authorized',
            'sendnf_id' => 'doc_xml_1',
        ]);

        Http::fake([
            'api.sendnf.test/v1/documents/doc_xml_1/xml' => Http::response('<nfse/>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $this->actingAs($user)
            ->get(route('admin.fiscal.nfse.xml', $nfse))
            ->assertOk()
            ->assertSee('<nfse', false);
    }

    public function test_pdf_404_quando_nota_nao_foi_transmitida(): void
    {
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.view']);

        $nfse = $this->novaNfse($user, [
            'idempotency_key' => 'idem-pdf-2',
            'status' => 'draft',
            'sendnf_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('admin.fiscal.nfse.pdf', $nfse))
            ->assertStatus(404);
    }

    public function test_isolamento_nao_aceita_clinica_do_request(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        [$clinic, $user] = $this->usuarioCom(['admin.view', 'fiscal.update']);

        Http::fake([
            'api.sendnf.test/v1/companies' => Http::response([
                'id' => 'cmp_iso',
                'external_id' => (string) $clinic->id,
                'cnpj' => '11222333000181',
                'state' => 'ready',
            ], 201),
        ]);

        // clinic_id vem somente do navegador e deve ser ignorado: o external_id
        // é sempre o da clínica autenticada (tenant), nunca o da requisição.
        $this->actingAs($user)
            ->post(route('admin.fiscal.register'), [
                'clinic_id' => 99999,
                'cnpj' => '11.222.333/0001-81',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->reconectarTenantParaAssercao();
        $this->assertTenantDatabaseHas('fiscal_companies', [
            'external_id' => (string) $clinic->id,
        ]);
        $this->assertTenantDatabaseMissing('fiscal_companies', [
            'external_id' => '99999',
        ]);
    }
}
