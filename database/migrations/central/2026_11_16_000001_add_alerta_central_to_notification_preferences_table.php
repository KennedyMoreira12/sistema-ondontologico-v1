<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alerta central na tela.
 *
 * Separado dos sons de propósito: o usuário pode querer o alerta visual sem
 * som (ou o contrário). O padrão é ligado, porque o alerta urgente existe para
 * ser visto, não para ficar escondido no sino.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('alerta_central')->default(true)->after('navegador_ativo');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('alerta_central');
        });
    }
};
