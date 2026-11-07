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
            if (! Schema::hasColumn('professionals', 'registro_profissional')) {
                $table->string('registro_profissional', 50)->nullable()->after('cro');
            }

            if (! Schema::hasColumn('professionals', 'conselho_regional')) {
                $table->string('conselho_regional', 20)->nullable()->after('registro_profissional');
            }
        });
    }

    public function down(): void
    {
        $colunas = array_values(array_filter(
            ['registro_profissional', 'conselho_regional'],
            fn (string $coluna): bool => Schema::hasColumn('professionals', $coluna)
        ));

        if ($colunas === []) {
            return;
        }

        Schema::table('professionals', function (Blueprint $table) use ($colunas) {
            $table->dropColumn($colunas);
        });
    }
};
