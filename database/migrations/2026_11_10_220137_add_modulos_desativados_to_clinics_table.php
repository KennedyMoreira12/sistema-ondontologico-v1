<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lista de módulos desligados para UMA clínica, mesmo quando o módulo é
     * comum a todas as verticais.
     *
     * A vertical continua decidindo o pacote padrão de módulos; esta coluna
     * serve para o caso pontual (ex.: a Malheiro não usa Turmas). Fica no
     * banco central porque é configuração da clínica, não dado clínico.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->json('modulos_desativados')->nullable()->after('verticals');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('modulos_desativados');
        });
    }
};
