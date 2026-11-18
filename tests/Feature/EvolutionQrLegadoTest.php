<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * As rotas `admin/evolution/qr` e `admin/test/evolution/qr` foram removidas.
 *
 * Elas ignoravam a clínica autenticada: usavam a instância fixa "malheiro", a
 * chave administrativa global e um cache sem prefixo de tenant. O efeito é que o
 * administrador de QUALQUER clínica recebia o QR da Malheiro — e quem escaneasse
 * aquele código conectava um aparelho ao WhatsApp dela.
 *
 * O caminho correto é `Platform\ClinicWhatsAppController`, que exige super admin
 * e lê instância e token do próprio registro da clínica.
 */
class EvolutionQrLegadoTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_rotas_de_qr_legado_nao_existem_mais(): void
    {
        $clinica = $this->criarClinica('Clínica Teste', 'teste@clinica.test');

        foreach (['/admin/evolution/qr', '/admin/test/evolution/qr'] as $url) {
            $this->actingAs($clinica['user'])
                ->get($url)
                ->assertNotFound();
        }
    }

    public function test_nomes_de_rota_do_qr_legado_nao_existem(): void
    {
        $this->assertFalse(Route::has('evolution.qr'));
        $this->assertFalse(Route::has('test.evolution.qr'));
    }
}
