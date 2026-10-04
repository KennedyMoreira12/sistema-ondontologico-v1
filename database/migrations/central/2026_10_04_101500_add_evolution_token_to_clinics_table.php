<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token da instância desta clínica na Evolution API.
 *
 * A Evolution tem dois níveis de chave: a global (`AUTHENTICATION_API_KEY`) e o
 * token por instância, que só alcança a própria. Guardamos o segundo porque o
 * primeiro é o mesmo para toda a plataforma: um vazamento dele daria acesso às
 * instâncias de todas as clínicas de uma vez. O token por instância limita o
 * dano a uma clínica — e é o que o `apikey` do payload do webhook carrega, o
 * que permite decidir se a requisição é da Evolution de verdade sem HMAC.
 *
 * Guardado em claro de propósito, e não com hash como o `integration_tokens`:
 * o Laravel precisa do valor original para chamar `/message/sendText/{instancia}`,
 * e um hash não volta atrás. O risco é mitigado pelo cast `encrypted` no model,
 * que guarda o texto cifrado com a APP_KEY — ler a coluna do banco não entrega o
 * segredo, e o dump do banco só serve com a APP_KEY junto.
 *
 * `nullable` porque a clínica pode existir antes da integração: o painel aceita
 * deixar em branco, e o webhook não resolve instância alguma para ela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->text('evolution_token')->nullable()->after('evolution_instance');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('evolution_token');
        });
    }
};
