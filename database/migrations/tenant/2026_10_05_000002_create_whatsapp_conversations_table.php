<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado da conversa de cada paciente com a recepcionista virtual.
 * Sem ele cada mensagem chega solta e o n8n não saberia se o paciente está
 * no meio de um cadastro ou de um agendamento. `etapa` é o estado atual da
 * máquina de estados e `dados` guarda o que já foi coletado na conversa.
 * `expires_at` delimita a validade do estado: conversa velha volta ao início.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('remote_jid', 191);
            $table->string('etapa', 60)->default('inicio');
            $table->json('dados')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('remote_jid', 'whatsapp_conversations_jid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
