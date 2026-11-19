<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A migration de provisionamento precisa sobreviver a uma aplicação pela metade.
 *
 * Em MariaDB um ALTER com várias colunas pode ser interrompido e deixar parte
 * delas criada. O guard antigo verificava só `whatsapp_status`: nesse estado a
 * reaplicação fazia `return` e as colunas restantes nunca apareciam.
 */
class MigrationWhatsappProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_reaplicacao_completa_as_colunas_faltantes(): void
    {
        $migration = $this->migration();

        $this->assertTrue(Schema::hasColumn('clinics', 'whatsapp_status'));
        $this->assertTrue(Schema::hasColumn('clinics', 'whatsapp_connected_at'));

        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_connected_at');
        });

        $this->assertFalse(Schema::hasColumn('clinics', 'whatsapp_connected_at'));

        $migration->up();

        foreach ([
            'whatsapp_status',
            'whatsapp_status_at',
            'whatsapp_last_error',
            'whatsapp_provisioned_at',
            'whatsapp_webhook_configured_at',
            'whatsapp_connected_at',
        ] as $coluna) {
            $this->assertTrue(Schema::hasColumn('clinics', $coluna), "A coluna {$coluna} deveria ter sido criada.");
        }
    }

    public function test_rollback_funciona_mesmo_com_algumas_colunas_ausentes(): void
    {
        $migration = $this->migration();

        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_last_error');
        });

        $migration->down();

        foreach ([
            'whatsapp_status',
            'whatsapp_status_at',
            'whatsapp_last_error',
            'whatsapp_provisioned_at',
            'whatsapp_webhook_configured_at',
            'whatsapp_connected_at',
        ] as $coluna) {
            $this->assertFalse(Schema::hasColumn('clinics', $coluna), "A coluna {$coluna} deveria ter sido removida.");
        }

        $this->assertTrue(Schema::hasColumn('clinics', 'evolution_token'), 'O rollback não pode tocar colunas antigas.');
    }

    private function migration(): Migration
    {
        return require database_path('migrations/central/2026_11_18_000004_add_whatsapp_provisioning_to_clinics_table.php');
    }
}
