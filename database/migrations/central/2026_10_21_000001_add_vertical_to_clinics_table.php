<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona vertical da clínica sem quebrar as existentes.
     * odontologia = padrão (compatível com clínica Malheiro)
     * fisioterapia = nova vertical
     *
     * IDEMPOTENTE para que a migration possa ser reaplicada sem derrubar a
     * fila caso a coluna já tenha sido criada.
     */
    public function up(): void
    {
        if (Schema::hasColumn('clinics', 'vertical')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->string('vertical')->default('odontologia')->after('status')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clinics', 'vertical')) {
            return;
        }

        if (Schema::hasIndex('clinics', ['vertical'])) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->dropIndex(['vertical']);
            });
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('vertical');
        });
    }
};
