<?php

namespace Tests\Unit;

use App\Support\RedatorDeSegredos;
use Tests\TestCase;

class RedatorDeSegredosTest extends TestCase
{
    public function test_nao_devolve_segredos_configurados_nem_tokens_ecoados_por_terceiros(): void
    {
        config([
            'services.evolution.admin_key' => 'chave-admin-secreta',
            'services.evolution.webhook_secret' => 'segredo-do-webhook',
            'services.n8n.webhook_token' => 'token-do-n8n',
        ]);

        $original = 'apikey=chave-admin-secreta; '
            .'{"error":"unauthorized","apikey":"token-da-clinica","x-evolution-secret":"segredo-do-webhook"}; '
            .'x-n8n-token: token-do-n8n';

        $redigido = RedatorDeSegredos::redigir($original);

        $this->assertStringNotContainsString('chave-admin-secreta', $redigido);
        $this->assertStringNotContainsString('token-da-clinica', $redigido);
        $this->assertStringNotContainsString('segredo-do-webhook', $redigido);
        $this->assertStringNotContainsString('token-do-n8n', $redigido);
        $this->assertStringContainsString('unauthorized', $redigido);
        $this->assertStringContainsString('***', $redigido);
    }

    public function test_mantem_diagnostico_e_aplica_limite(): void
    {
        $redigido = RedatorDeSegredos::redigir('HTTP 500: '.str_repeat('a', 600), 100);

        $this->assertStringStartsWith('HTTP 500:', $redigido);
        $this->assertLessThanOrEqual(101, mb_strlen($redigido));
    }
}
