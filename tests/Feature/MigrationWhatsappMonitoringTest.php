<?php

namespace Tests\Feature;

use App\Models\Central\Clinic;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationWhatsappMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_cria_colunas_de_estado_e_legacy(): void
    {
        $migration = $this->migration();

        foreach ($this->colunas() as $coluna) {
            $this->assertTrue(Schema::hasColumn('clinics', $coluna), "A coluna {$coluna} deveria existir.");
        }

        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_current_state');
        });

        $migration->up();

        $this->assertTrue(Schema::hasColumn('clinics', 'whatsapp_current_state'));
    }

    public function test_migration_preserva_historico_de_conexao_existente(): void
    {
        $clinica = Clinic::create([
            'name' => 'Clínica Histórica',
            'email' => 'historica@teste.test',
            'status' => 'active',
            'is_active' => true,
        ]);

        DB::table('clinics')->where('id', $clinica->id)->update([
            'whatsapp_connected_at' => now()->subDay(),
            'whatsapp_last_connected_at' => null,
        ]);

        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_last_connected_at');
        });

        $this->migration()->up();

        $registro = DB::table('clinics')->where('id', $clinica->id)->first();

        $this->assertNotNull($registro->whatsapp_last_connected_at);
    }

    public function test_rollback_remove_apenas_as_colunas_novas(): void
    {
        $this->migration()->down();

        foreach ($this->colunas() as $coluna) {
            $this->assertFalse(Schema::hasColumn('clinics', $coluna), "A coluna {$coluna} deveria ter sido removida.");
        }

        $this->assertTrue(Schema::hasColumn('clinics', 'whatsapp_connected_at'));
        $this->assertTrue(Schema::hasColumn('clinics', 'evolution_token'));
        $this->assertTrue(Schema::hasColumn('clinics', 'n8n_webhook_url'));
    }

    /**
     * @return list<string>
     */
    private function colunas(): array
    {
        return [
            'whatsapp_last_connected_at',
            'whatsapp_current_state',
            'whatsapp_current_state_at',
            'whatsapp_legacy_adotado_at',
        ];
    }

    private function migration(): Migration
    {
        return require database_path('migrations/central/2026_11_19_000001_add_whatsapp_monitoring_state_to_clinics_table.php');
    }
}
