<?php

namespace Tests\Unit\Fiscal;

use App\Services\Fiscal\SendNfClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendNfClientTimeoutTest extends TestCase
{
    protected function tearDown(): void
    {
        Http::preventStrayRequests();
        parent::tearDown();
    }

    public function test_timeout_gera_mensagem_segura(): void
    {
        Config::set('fiscal.sendnf.base_url', 'https://sendnf.example.test');
        Config::set('fiscal.sendnf.api_key', 'test-key-123');

        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Falha de conexão com SendNF.');

        $client = new SendNfClient;
        $client->get('/ping');
    }

    public function test_idempotency_header_pode_ser_enviado(): void
    {
        Config::set('fiscal.sendnf.base_url', 'https://sendnf.example.test');
        Config::set('fiscal.sendnf.api_key', 'test-key-123');

        Http::fake([
            'sendnf.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $client = new SendNfClient;
        $result = $client->post('/preview_nfse', ['x' => 1], ['Idempotency-Key' => 'nfse_abc123']);
        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Idempotency-Key');
        });
    }
}
