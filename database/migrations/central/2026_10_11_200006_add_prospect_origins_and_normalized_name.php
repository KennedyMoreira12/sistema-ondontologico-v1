<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ADITIVA da Etapa 2.
 *
 * - `prospects.name_normalized`: nome sem acento/pontuação, para casar duplicatas
 *   sem depender de comparação sensível a acento no banco.
 * - `prospect_imports.rejected`: contagem de linhas rejeitadas (o schema da
 *   Etapa 1 não previa esse número).
 * - `prospect_origins`: guarda TODAS as procedências de um contato, o que
 *   preserva as duas listas quando o mesmo telefone/nome aparece nas duas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('name_normalized')->nullable()->index();
        });

        Schema::table('prospect_imports', function (Blueprint $table) {
            $table->unsignedInteger('rejected')->default(0);
        });

        Schema::create('prospect_origins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->string('source');
            $table->string('source_file')->nullable();
            $table->string('source_ref')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_origins');

        Schema::table('prospect_imports', function (Blueprint $table) {
            $table->dropColumn('rejected');
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('name_normalized');
        });
    }
};
