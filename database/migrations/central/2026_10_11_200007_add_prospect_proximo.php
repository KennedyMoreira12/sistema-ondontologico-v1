<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colunas de acompanhamento do contato (prospecção).
 *
 * IDEMPOTENTE: as três colunas já existem na produção, mas o registro desta
 * migration não foi gravado. Sem as guardas, `migrate` falharia em
 * "duplicate column" e interromperia a fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('prospects', 'proximo')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->string('proximo', 30)->nullable()->after('next_follow_up_at');
            });
        }

        if (! Schema::hasColumn('prospects', 'bot_pause')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->boolean('bot_pause')->default(false)->after('proximo');
            });
        }

        if (! Schema::hasColumn('prospects', 'bot_paused_at')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->dateTime('bot_paused_at')->nullable()->after('bot_pause');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prospects', 'bot_paused_at')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->dropColumn('bot_paused_at');
            });
        }

        if (Schema::hasColumn('prospects', 'bot_pause')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->dropColumn('bot_pause');
            });
        }

        if (Schema::hasColumn('prospects', 'proximo')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->dropColumn('proximo');
            });
        }
    }
};
