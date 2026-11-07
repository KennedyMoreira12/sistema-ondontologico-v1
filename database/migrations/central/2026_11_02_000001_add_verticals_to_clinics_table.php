<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Múltiplas verticais por clínica.
 *
 * A coluna `vertical` (string, default `odontologia`) permanece como a
 * vertical primária - usada por todo o código existente e pelos testes.
 * A nova coluna `verticals` (JSON) lista TODAS as verticais habilitadas
 * para a clínica, permitindo que a Clínica Daiane ative simultaneamente
 * fisioterapia, pilates e depilação a laser, enquanto a Malheiro continua
 * apenas com odontologia.
 *
 * Quando `verticals` estiver nula, o resolvedor cai de volta para
 * `[vertical()]` - comportamento idêntico ao de antes.
 *
 * IDEMPOTENTE para que a migration possa ser reaplicada sem derrubar a fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clinics', 'verticals')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->json('verticals')->nullable()->after('vertical');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clinics', 'verticals')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('verticals');
        });
    }
};
