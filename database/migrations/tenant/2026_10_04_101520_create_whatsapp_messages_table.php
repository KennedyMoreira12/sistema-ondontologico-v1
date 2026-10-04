<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensagens que passaram pelo WhatsApp da clínica.
 *
 * A Evolution reenvia: o webhook dela tem retry com backoff, e uma resposta
 * lenta ou um 5xx no meio do caminho faz a mesma mensagem chegar de novo. Sem
 * registro, cada reentrega viraria um atendimento novo no n8n — e o bot, que é
 * idempotente por `idempotency_key`, ainda criaria duas consultas porque cada
 * execução enxerga um atendimento distinto.
 *
 * O índice único em `message_key` é o que fecha isso. Ele não depende de a
 * checagem "já existe?" acertar: dois workers disputam o insert e o perdedor
 * leva violação de unicidade. `message_key` sozinho não serviria, porque o
 * WhatsApp reusa o mesmo id nos dois sentidos da conversa; `from_me` separa
 * "isto é da clínica" de "isto é do paciente".
 *
 * `forwarded_at` e `forward_error` existem para o caso de o n8n estar fora do
 * ar: a mensagem fica registrada como pendente em vez de ser perdida, e o
 * operador enxerga o que não saiu. Sem eles, uma falha do n8n seria indistinguível
 * de uma mensagem que nunca chegou.
 *
 * O conteúdo cru da Evolution não é guardado. `payload` recebe só o essencial
 * (remote_jid, push_name, texto, tipo), porque o JSON completo traz mediaKey,
 * sessionId e metadados do Baileys que não são usados por nada aqui — e são
 * exatamente o tipo de campo que costuma carregar dado de sessão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();

            $table->string('message_key', 128);
            $table->boolean('from_me')->default(false);
            $table->string('event', 40)->nullable();
            $table->string('message_type', 40)->nullable();
            $table->string('remote_jid', 128)->nullable();
            $table->string('push_name', 191)->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->text('forward_error')->nullable();

            $table->timestamps();

            $table->unique(['message_key', 'from_me'], 'whatsapp_messages_key_unique');
            $table->index('remote_jid', 'whatsapp_messages_remote_jid_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
