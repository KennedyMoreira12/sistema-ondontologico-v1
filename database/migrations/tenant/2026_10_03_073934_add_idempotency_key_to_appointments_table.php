<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chave de idempotência do agendamento feito pelo bot.
 *
 * O n8n reexecuta webhook — o próprio n8n repete entrega quando o nó demora, e
 * qualquer reentrega do webhook duplica a requisição. Sem esta chave, um retry
 * criava uma segunda consulta para o mesmo paciente no mesmo horário.
 *
 * O índice é único, e é ele que fecha a corrida: dois workers com a mesma chave
 * disputam o insert e o perdedor leva DuplicateEntry, em vez de criar a
 * duplicata que a checagem "já existe?" não consegue evitar.
 *
 * `nullable` de propósito: agendamento criado na tela, e não pelo bot, não tem
 * chave — e no MySQL vários `NULL` convivem num índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('titulo');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
