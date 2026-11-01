<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colunas do atendimento com IA.
 *
 * São aditivas e todas anuláveis (ou com default): bancos de clínica já em
 * produção ganham as colunas sem reescrever as 11k mensagens existentes e sem
 * exigir backfill. Sem elas o Laravel não tem onde registrar em que pé está o
 * bot de cada conversa, nem o que a IA respondeu a cada mensagem.
 *
 * `bot_status` nasce `ativo`, que é o comportamento de hoje para quem nunca
 * pausou nada. O nome é deliberadamente diferente de `etapa`: `etapa` é onde a
 * conversa está (menu, horario), `bot_status` é QUEM responde (o bot ou um
 * atendente humano). Misturar os dois faria uma pausa virar um estado de
 * conversa e o paciente cair no `default` do switch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            // ativo | pausado | aguardando_atendente | humano
            $table->string('bot_status', 20)->default('ativo');
            $table->timestamp('pausado_ate')->nullable();
            $table->timestamp('humano_iniciado_em')->nullable();
            $table->unsignedSmallInteger('ia_mensagens')->default(0);
            $table->string('ia_ultimo_erro', 255)->nullable();
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('ai_intent', 40)->nullable();
            $table->boolean('ai_handled')->nullable();
            $table->string('ai_error', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'bot_status',
                'pausado_ate',
                'humano_iniciado_em',
                'ia_mensagens',
                'ia_ultimo_erro',
            ]);
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['ai_intent', 'ai_handled', 'ai_error']);
        });
    }
};
