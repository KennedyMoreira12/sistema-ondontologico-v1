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
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('vertical')->default('odontologia')->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropIndex(['vertical']);
            $table->dropColumn('vertical');
        });
    }
};
