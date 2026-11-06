<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox transacional das mensagens que a clínica envia.
 *
 * Antes desta tabela, o envio era uma chamada HTTP dentro do request: se a
 * Evolution caísse depois de o n8n receber 200, a resposta se perdia sem
 * registro; se o processo morresse entre o envio e a gravação do histórico,
 * a mensagem existia no WhatsApp e não no banco.
 *
 * A outbox separa "decidir enviar" de "enviar". O registro nasce antes da
 * chamada, o job é idempotente pelo `message_key`, e a recuperação encontra o
 * que ficou pendente depois de um restart.
 *
 * `status`:
 * - pending: aguardando o job (ou nova tentativa depois de `available_at`);
 * - processing: um worker reservou a linha;
 * - sent: a Evolution aceitou o envio;
 * - dead: falha definitiva ou tentativas esgotadas (DLQ).
 *
 * Ela NÃO promete entrega: a Evolution devolve "aceito", e os eventos
 * `MESSAGES_UPDATE` é que movem `whatsapp_messages.delivery_status` para
 * sent/delivered/read quando o provedor os envia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_outbox', function (Blueprint $table) {
            $table->id();

            $table->string('message_key', 191)->unique();
            $table->string('remote_jid', 191);
            $table->string('telefone', 32)->nullable();

            $table->string('kind', 20)->default('text');
            $table->text('body')->nullable();
            $table->text('media_url')->nullable();
            $table->string('media_type', 40)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('file_name', 191)->nullable();
            $table->string('caption', 1000)->nullable();

            $table->json('payload')->nullable();
            $table->string('source', 40)->nullable();

            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);

            $table->timestamp('available_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->string('provider_message_id', 191)->nullable();
            $table->text('last_error')->nullable();

            // Código estável da última falha (`sem_integracao`, `evolution_recusou`,
            // `incerto`, `exception`). Existe para o operador e para a API
            // decidirem 503/502 sem interpretar o texto do erro, que muda de
            // versão para versão da Evolution.
            $table->string('last_error_code', 40)->nullable();
            $table->string('correlation_id', 64)->nullable();

            $table->timestamps();

            $table->index(['status', 'available_at'], 'whatsapp_outbox_status_available_index');
            $table->index(['status', 'locked_at'], 'whatsapp_outbox_status_locked_index');
            $table->index('provider_message_id', 'whatsapp_outbox_provider_id_index');
            $table->index('correlation_id', 'whatsapp_outbox_correlation_index');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // pending | sent | delivered | read | failed. Nulo nas linhas
            // antigas: não sabemos o estado delas e inventar "sent" seria
            // afirmar uma entrega que ninguém confirmou.
            $table->string('delivery_status', 20)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('correlation_id', 64)->nullable();

            $table->index('delivery_status', 'whatsapp_messages_delivery_status_index');
            $table->index('correlation_id', 'whatsapp_messages_correlation_index');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('whatsapp_messages_delivery_status_index');
            $table->dropIndex('whatsapp_messages_correlation_index');
            $table->dropColumn(['delivery_status', 'delivered_at', 'read_at', 'correlation_id']);
        });

        Schema::dropIfExists('whatsapp_outbox');
    }
};
