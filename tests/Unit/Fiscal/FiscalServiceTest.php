<?php

namespace Tests\Unit\Fiscal;

use App\Models\FiscalCompany;
use App\Services\Fiscal\FiscalService;
use App\Services\Fiscal\SendNfClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class FiscalServiceTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

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

    protected function service(): FiscalService
    {
        return new FiscalService(new SendNfClient);
    }

    public function test_emitir_bloqueado_quando_integracao_desabilitada(): void
    {
        $this->criarClinicaTeste();

        $this->assertFalse($this->service()->canEmit());
    }

    public function test_emitir_bloqueado_quando_empresa_nao_esta_habilitada_localmente(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $this->criarClinicaTeste();

        $this->assertFalse($this->service()->canEmit());
    }

    public function test_emitir_bloqueado_quando_status_nao_eh_ready(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $clinic = $this->criarClinicaTeste();

        FiscalCompany::updateOrCreate(
            ['external_id' => (string) $clinic->id],
            ['enabled' => true, 'status' => 'not_configured', 'sendnf_company_id' => 'cmp_1']
        );

        $this->assertFalse($this->service()->canEmit());
    }

    public function test_emitir_bloqueado_sem_empresa_cadastrada_na_sendnf(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $clinic = $this->criarClinicaTeste();

        FiscalCompany::updateOrCreate(
            ['external_id' => (string) $clinic->id],
            ['enabled' => true, 'status' => 'ready', 'sendnf_company_id' => null]
        );

        $this->assertFalse($this->service()->canEmit());
    }

    public function test_emitir_liberado_apenas_com_todos_os_requisitos(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $clinic = $this->criarClinicaTeste();

        FiscalCompany::updateOrCreate(
            ['external_id' => (string) $clinic->id],
            ['enabled' => true, 'status' => 'ready', 'sendnf_company_id' => 'cmp_1']
        );

        $this->assertTrue($this->service()->canEmit());
    }

    public function test_register_company_cria_registro_no_tenant(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        [$clinic, $user] = $this->criarUsuarioComPermissao();
        $this->actingAs($user);

        Http::fake([
            'api.sendnf.test/v1/companies' => Http::response([
                'id' => 'cmp_abc',
                'external_id' => (string) $clinic->id,
                'cnpj' => '11222333000181',
                'razao_social' => 'Clinica Exemplo Ltda',
                'state' => 'ready',
            ], 201),
        ]);

        $resultado = $this->service()->registerCompany((string) $clinic->id, '11.222.333/0001-81');

        $this->assertSame('cmp_abc', $resultado['company']->sendnf_company_id);
        $this->assertSame('11222333000181', $resultado['company']->cnpj);
        $this->assertSame((string) $clinic->id, $resultado['company']->external_id);
        $this->assertSame('ready', $resultado['company']->status);

        $this->assertTenantDatabaseHas('fiscal_companies', [
            'external_id' => (string) $clinic->id,
            'sendnf_company_id' => 'cmp_abc',
        ]);
    }

    public function test_register_company_rejeita_cnpj_invalido(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $this->criarClinicaTeste();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CNPJ inválido.');

        $this->service()->registerCompany('clinica-1', 'não é um cnpj');
    }

    public function test_register_company_nao_faz_requisicao_quando_desabilitado(): void
    {
        Config::set('fiscal.sendnf.enabled', false);
        Http::preventStrayRequests();
        $this->criarClinicaTeste();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Integração SendNF desabilitada.');

        $this->service()->registerCompany('clinica-1', '11.222.333/0001-81');
    }

    public function test_sync_readiness_atualiza_status_apenas_apos_consulta_da_api(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $clinic = $this->criarClinicaTeste();

        $company = FiscalCompany::updateOrCreate(
            ['external_id' => (string) $clinic->id],
            ['sendnf_company_id' => 'cmp_abc', 'status' => 'not_configured']
        );

        Http::fake([
            'api.sendnf.test/v1/companies/cmp_abc/readiness' => Http::response([
                'state' => 'ready',
                'nfse_ready' => true,
                'missing' => [],
            ], 200),
        ]);

        $resultado = $this->service()->syncReadiness($company);

        $this->assertTrue($resultado['readiness']['nfse_ready']);
        $this->assertSame('ready', $resultado['company']->fresh()->status);
        $this->assertNotNull($resultado['company']->fresh()->last_check_at);
    }

    public function test_sync_readiness_erro_quando_empresa_nao_cadastrada(): void
    {
        $this->criarClinicaTeste();

        $company = FiscalCompany::current();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Empresa não cadastrada na SendNF.');

        $this->service()->syncReadiness($company);
    }

    public function test_preview_draft_gera_rascunho_com_idempotencia(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $clinic = $this->criarClinicaTeste();

        $company = FiscalCompany::updateOrCreate(
            ['external_id' => (string) $clinic->id],
            ['sendnf_company_id' => 'cmp_abc']
        );

        Http::fake([
            'api.sendnf.test/v1/nfse/preview' => Http::response([
                'id' => 'prev_1',
                'status' => 'pending',
                'warnings' => [],
            ], 200),
        ]);

        $dados = [
            'idempotency_key' => 'idem-preview-1',
            'tomador_documento' => '529.982.247-25',
            'tomador_nome' => 'Maria Silva',
            'servico_descricao' => 'Consulta odontológica',
            'valor_servico_cents' => 15000,
        ];

        $r1 = $this->service()->previewDraft($company, $dados);
        $r2 = $this->service()->previewDraft($company, $dados);

        // Mesma chave de idempotência: mesmo rascunho, sem duplicar.
        $this->assertSame($r1['draft']->id, $r2['draft']->id);
        $this->assertSame('idem-preview-1', $r1['draft']->idempotency_key);
        $this->assertSame('pending', $r1['preview']['status']);

        $this->assertTenantDatabaseCount('nfse', 1);
    }

    public function test_preview_draft_sem_chave_gera_chave_deterministica(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $clinic = $this->criarClinicaTeste();

        $company = FiscalCompany::updateOrCreate(
            ['external_id' => (string) $clinic->id],
            ['sendnf_company_id' => 'cmp_abc']
        );

        Http::fake([
            'api.sendnf.test/v1/nfse/preview' => Http::response(['id' => 'prev_1', 'status' => 'pending'], 200),
        ]);

        $dados = [
            'tomador_documento' => '529.982.247-25',
            'tomador_nome' => 'Maria Silva',
            'servico_descricao' => 'Consulta',
            'valor_servico_cents' => 15000,
        ];

        $r1 = $this->service()->previewDraft($company, $dados);
        $r2 = $this->service()->previewDraft($company, $dados);

        $this->assertSame($r1['draft']->idempotency_key, $r2['draft']->idempotency_key);
        $this->assertStringStartsWith('nfse_', $r1['draft']->idempotency_key);
    }

    public function test_create_draft_nfse_respeita_concorrencia_e_idempotencia(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $clinic = $this->criarClinicaTeste();

        $company = FiscalCompany::updateOrCreate(
            ['external_id' => (string) $clinic->id],
            ['sendnf_company_id' => 'cmp_abc']
        );

        $dados = [
            'idempotency_key' => 'idem-draft-1',
            'tomador_documento' => '529.982.247-25',
            'tomador_nome' => 'Maria Silva',
            'servico_descricao' => 'Consulta',
            'valor_servico_cents' => 15000,
            'status' => 'draft',
        ];

        $n1 = $this->service()->createDraftNfse($company, $dados);
        $n2 = $this->service()->createDraftNfse($company, $dados);

        $this->assertSame($n1->id, $n2->id);
        $this->assertTenantDatabaseCount('nfse', 1);
    }

    public function test_associacao_empresa_a_clinica_por_external_id(): void
    {
        Config::set('fiscal.sendnf.enabled', true);
        $clinic = $this->criarClinicaTeste();

        FiscalCompany::updateOrCreate(
            ['external_id' => (string) $clinic->id],
            ['cnpj' => '11222333000181', 'sendnf_company_id' => 'cmp_a']
        );

        // O registro fiscal fica no banco desta clínica (tenant), vinculado ao
        // id central da clínica via external_id. Não há clinic_id na tabela: o
        // isolamento é a própria conexão `tenant`.
        $this->assertTenantDatabaseHas('fiscal_companies', [
            'external_id' => (string) $clinic->id,
            'sendnf_company_id' => 'cmp_a',
        ]);

        $empresa = FiscalCompany::current();
        $this->assertSame((string) $clinic->id, $empresa->external_id);
    }
}
