<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona registro profissional (CREFITO, etc.) sem remover CRO.
     * Mudança aditiva para compatibilidade com fisioterapia.
     */
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->string('registro_profissional', 50)->nullable()->after('cro');
            $table->string('conselho_regional', 20)->nullable()->after('registro_profissional');
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropColumn(['registro_profissional', 'conselho_regional']);
        });
    }
};
