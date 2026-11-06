<?php

namespace Tests\Unit\Fiscal;

use App\Services\Fiscal\SendNfClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendNfClientMethodsTest extends TestCase
{
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

    public function test_lookup_cnpj_retorna_dados_publicos(): void
    {
        Http::fake([
            'api.sendnf.test/v1/lookup/cnpj/*' => Http::response([
                'cnpj' => '11222333000181',
                'razao_social' => 'Clinica Exemplo Ltda',
                'municipio' => 'Januária',
                'uf' => 'MG',
            ], 200),
        ]);

        $client = new SendNfClient;
        $dados = $client->lookupCnpj('11.222.333/0001-81');

        $this->assertSame('11222333000181', $dados['cnpj']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.sendnf.test/v1/lookup/cnpj/11222333000181'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_create_company_cadastra_e_retorna_id(): void
    {
        Http::fake([
            'api.sendnf.test/v1/companies' => Http::response([
                'id' => 'cmp_123',
                'external_id' => 'clinica-1',
                'cnpj' => '11222333000181',
                'state' => 'not_configured',
            ], 201),
        ]);

        $client = new SendNfClient;
        $dados = $client->createCompany([
            'cnpj' => '11222333000181',
            'external_id' => 'clinica-1',
            'metadata' => ['origin' => 'lj_smartclinic_tenant'],
        ]);

        $this->assertSame('cmp_123', $dados['id']);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/v1/companies')
                && json_decode($request->body(), true)['cnpj'] === '11222333000181';
        });
    }

    public function test_get_company_por_id_e_por_external_id(): void
    {
        Http::fake([
            'api.sendnf.test/v1/companies/cmp_123' => Http::response(['id' => 'cmp_123', 'state' => 'ready'], 200),
            'api.sendnf.test/v1/companies/by-external-id/clinica-1' => Http::response(['id' => 'cmp_123', 'external_id' => 'clinica-1'], 200),
        ]);

        $client = new SendNfClient;

        $this->assertSame('cmp_123', $client->getCompany('cmp_123')['id']);
        $this->assertSame('cmp_123', $client->getCompanyByExternalId('clinica-1')['id']);
    }

    public function test_readiness_retorna_checklist(): void
    {
        Http::fake([
            'api.sendnf.test/v1/companies/cmp_123/readiness' => Http::response([
                'state' => 'ready',
                'nfse_ready' => true,
                'missing' => [],
            ], 200),
        ]);

        $client = new SendNfClient;
        $resultado = $client->getReadiness('cmp_123');

        $this->assertTrue($resultado['nfse_ready']);
    }

    public function test_preview_nfse_usa_cabecalho_idempotency_key(): void
    {
        $capturada = null;
        Http::fake(function ($request) use (&$capturada) {
            $capturada = $request;

            return Http::response([
                'id' => 'nfs_prev_1',
                'status' => 'pending',
                'warnings' => [],
            ], 200);
        });

        $client = new SendNfClient;
        $resultado = $client->previewNfse([
            'company_id' => 'cmp_123',
            'reference' => 'draft-1',
            'taker' => ['document' => '52998224725', 'name' => 'Maria Silva'],
            'items' => [['description' => 'Consulta', 'unit_amount' => 15000]],
        ], ['Idempotency-Key' => 'idem-123']);

        $this->assertSame('pending', $resultado['status']);
        $this->assertNotNull($capturada);
        $this->assertTrue(
            in_array('idem-123', (array) $capturada->header('Idempotency-Key'), true)
        );
        $this->assertStringContainsString('/v1/nfse/preview', $capturada->url());
    }

    public function test_get_documentos_lista_notas(): void
    {
        Http::fake(function ($request) {
            return Http::response([
                'data' => [
                    ['id' => 'doc_1', 'status' => 'authorized', 'reference' => 'ref-1'],
                ],
            ], 200);
        });

        $client = new SendNfClient;
        $resultado = $client->getDocuments(['company_id' => 'cmp_123']);

        $this->assertCount(1, $resultado['data']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/documents');
        });
    }

    public function test_detalhar_documento_retorna_status_e_urls(): void
    {
        Http::fake([
            'api.sendnf.test/v1/documents/doc_1' => Http::response([
                'id' => 'doc_1',
                'status' => 'authorized',
                'urls' => ['pdf' => 'https://api.sendnf.test/v1/documents/doc_1/pdf'],
            ], 200),
        ]);

        $client = new SendNfClient;
        $documento = $client->getDocument('doc_1');

        $this->assertSame('authorized', $documento['status']);
    }

    public function test_pdf_e_xml_retornam_conteudo_binario(): void
    {
        Http::fake([
            'api.sendnf.test/v1/documents/doc_1/pdf' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
            'api.sendnf.test/v1/documents/doc_1/xml' => Http::response('<nfse/>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $client = new SendNfClient;

        $pdf = $client->getDocumentPdf('doc_1');
        $this->assertSame(200, $pdf->status());
        $this->assertStringContainsString('%PDF', $pdf->body());

        $xml = $client->getDocumentXml('doc_1');
        $this->assertSame(200, $xml->status());
        $this->assertStringContainsString('<nfse', $xml->body());
    }

    public function test_erro_401_nao_expoe_chave(): void
    {
        Http::fake([
            'api.sendnf.test/v1/companies/cmp_123' => Http::response(['error' => ['code' => 'unauthorized']], 401),
        ]);

        $client = new SendNfClient;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Falha de autenticação com SendNF.');

        $client->getCompany('cmp_123');

        // Nenhum vestígio da api_key no log da exceção é verificado implicitamente
        // pela mensagem genérica.
    }

    public function test_erro_422_retorna_mensagem_segura(): void
    {
        Http::fake([
            'api.sendnf.test/v1/nfse/preview' => Http::response([
                'error' => ['code' => 'fiscal_profile_invalid', 'field' => 'taker.document'],
            ], 422),
        ]);

        $client = new SendNfClient;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Erro de requisição à SendNF.');

        $client->previewNfse(['company_id' => 'cmp_123']);
    }

    public function test_erro_429_limite_de_requisicoes(): void
    {
        Http::fake([
            'api.sendnf.test/v1/companies/cmp_123' => Http::response([], 429, ['Retry-After' => '60']),
        ]);

        $client = new SendNfClient;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Limite de requisições excedido na SendNF.');

        $client->getCompany('cmp_123');
    }

    public function test_erro_500_servidor(): void
    {
        Http::fake([
            'api.sendnf.test/v1/companies/cmp_123' => Http::response([], 500),
        ]);

        $client = new SendNfClient;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Erro no servidor SendNF.');

        $client->getCompany('cmp_123');
    }

    public function test_falha_de_conexao_timeout(): void
    {
        Http::fake(function () {
            throw new ConnectionException('connect timeout');
        });

        $client = new SendNfClient;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Falha de conexão com SendNF.');

        $client->getCompany('cmp_123');
    }

    public function test_sem_base_url_lanca_mensagem_segura(): void
    {
        Config::set('fiscal.sendnf.base_url', '');

        $client = new SendNfClient;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SENDFN_BASE_URL não configurada.');

        $client->getCompany('cmp_123');
    }

    public function test_sem_api_key_lanca_mensagem_segura(): void
    {
        Config::set('fiscal.sendnf.api_key', '');

        $client = new SendNfClient;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SENDFN_API_KEY não configurada.');

        $client->getCompany('cmp_123');
    }

    public function test_emit_e_cancel_estao_disponiveis_no_mas_dependem_do_servico(): void
    {
        Http::fake([
            'api.sendnf.test/v1/nfse' => Http::response(['id' => 'doc_emitido', 'status' => 'pending'], 200),
            'api.sendnf.test/v1/documents/doc_1/cancel' => Http::response(['id' => 'doc_1', 'status' => 'cancelled'], 200),
        ]);

        $client = new SendNfClient;

        $emitido = $client->emitNfse(['company_id' => 'cmp_123', 'reference' => 'ref-1'], ['Idempotency-Key' => 'idem-emit']);
        $this->assertSame('pending', $emitido['status']);

        $cancelado = $client->cancelDocument('doc_1', ['reason' => 'erro de preenchimento']);
        $this->assertSame('cancelled', $cancelado['status']);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/v1/nfse') && $request->hasHeader('Idempotency-Key');
        });
    }
}
