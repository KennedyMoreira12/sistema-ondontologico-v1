<?php

namespace Tests\Unit\Fiscal;

use App\Services\Fiscal\SendNfClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendNfClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Http::preventStrayRequests();
        parent::tearDown();
    }

    public function test_get_retorna_array_quando_resposta_sucesso(): void
    {
        Config::set('fiscal.sendnf.base_url', 'https://sendnf.example.test');
        Config::set('fiscal.sendnf.api_key', 'test-key-123');

        Http::fake([
            'sendnf.example.test/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $client = new SendNfClient;
        $result = $client->get('/ping');

        $this->assertIsArray($result);
        $this->assertSame('ok', $result['status']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendnf.example.test/ping')
                && $request->hasHeader('Authorization')
                && str_contains($request->header('Authorization')[0], 'Bearer');
        });
    }

    public function test_post_envia_payload_e_retorna_json(): void
    {
        Config::set('fiscal.sendnf.base_url', 'https://sendnf.example.test');
        Config::set('fiscal.sendnf.api_key', 'test-key-123');

        Http::fake([
            'sendnf.example.test/*' => Http::response(['company_id' => 'abc123'], 201),
        ]);

        $client = new SendNfClient;
        $result = $client->post('/create_company', ['cnpj' => '00000000000191']);

        $this->assertSame('abc123', $result['company_id']);
    }

    public function test_sem_base_url_lanca_excecao_segura(): void
    {
        Config::set('fiscal.sendnf.base_url', '');
        Config::set('fiscal.sendnf.api_key', 'test-key-123');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SENDFN_BASE_URL não configurada.');

        $client = new SendNfClient;
        $client->get('/test');
    }

    public function test_sem_api_key_lanca_excecao_segura(): void
    {
        Config::set('fiscal.sendnf.base_url', 'https://sendnf.example.test');
        Config::set('fiscal.sendnf.api_key', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SENDFN_API_KEY não configurada.');

        $client = new SendNfClient;
        $client->get('/test');
    }

    public function test_erro_401_403_nao_expoe_chave(): void
    {
        Config::set('fiscal.sendnf.base_url', 'https://sendnf.example.test');
        Config::set('fiscal.sendnf.api_key', 'test-key-123');

        Http::fake([
            'sendnf.example.test/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Falha de autenticação com SendNF.');

        $client = new SendNfClient;
        $client->get('/test');
    }

    public function test_erro_429_rate_limit(): void
    {
        Config::set('fiscal.sendnf.base_url', 'https://sendnf.example.test');
        Config::set('fiscal.sendnf.api_key', 'test-key-123');

        Http::fake([
            'sendnf.example.test/*' => Http::response(['error' => 'too_many'], 429),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Limite de requisições excedido na SendNF.');

        $client = new SendNfClient;
        $client->get('/test');
    }

    public function test_erro_422_validacao(): void
    {
        Config::set('fiscal.sendnf.base_url', 'https://sendnf.example.test');
        Config::set('fiscal.sendnf.api_key', 'test-key-123');

        Http::fake([
            'sendnf.example.test/*' => Http::response(['error' => 'invalid'], 422),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Erro de requisição à SendNF.');

        $client = new SendNfClient;
        $client->get('/test');
    }

    public function test_erro_500_servidor(): void
    {
        Config::set('fiscal.sendnf.base_url', 'https://sendnf.example.test');
        Config::set('fiscal.sendnf.api_key', 'test-key-123');

        Http::fake([
            'sendnf.example.test/*' => Http::response(['error' => 'server'], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Erro no servidor SendNF.');

        $client = new SendNfClient;
        $client->get('/test');
    }
}
