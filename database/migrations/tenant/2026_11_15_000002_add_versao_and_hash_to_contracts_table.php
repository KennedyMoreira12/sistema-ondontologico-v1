<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versionamento do termo de responsabilidade.
 *
 * `versao` sobe quando o texto muda depois de assinado e `conteudo_hash`
 * amarra a versão ao texto exato. É isso que permite exigir nova assinatura
 * sem perder a anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedInteger('versao')->default(1);
            $table->string('conteudo_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['versao', 'conteudo_hash']);
        });
    }
};
