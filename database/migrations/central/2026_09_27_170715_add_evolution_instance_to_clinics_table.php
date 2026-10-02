<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nome da instância desta clínica na Evolution API (o "Marlene" de cada
     * clínica).
     *
     * É a chave com que o n8n descobre de quem é a mensagem que chegou: o
     * webhook traz o nome da instância, e o n8n troca por URL da API e token
     * daquela clínica. Sem esta coluna, uma clínica por instância do WhatsApp
     * não tem como ser identificada, e o `CLINICAS_MAP` do n8n vira uma lista
     * solta sem dono.
     *
     * Único de propósito: duas clínicas na mesma instância significam um
     * número de WhatsApp compartilhado, e o n8n não conseguiria dizer qual
     * banco usar — o roteamento viraria o primeiro da lista, que é o jeito
     * mais caro de errar num sistema multi-tenant.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('evolution_instance')->nullable()->unique()->after('whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropUnique(['evolution_instance']);
            $table->dropColumn('evolution_instance');
        });
    }
};
