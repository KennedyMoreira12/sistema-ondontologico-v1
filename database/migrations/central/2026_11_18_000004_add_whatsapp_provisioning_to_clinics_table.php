<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado do WhatsApp provisionado de cada clínica.
     *
     * A instância e o token já existiam, mas nada registrava se o webhook foi
     * configurado, se a conexão abriu e qual foi o último erro. Sem isso o
     * painel não distingue "clínica sem WhatsApp contratado" de "instância
     * criada e ninguém escaneou o QR" de "estava conectada e caiu" — e o
     * atendimento automático pode começar a responder antes de o webhook e o
     * roteamento estarem de pé.
     *
     * `whatsapp_provisioned_at` é a marca de que a clínica passou pelo
     * provisionamento automático. Clínicas antigas ficam com ela nula e seguem
     * o comportamento anterior: nada aqui as bloqueia retroativamente.
     *
     * IDEMPOTENTE para poder ser reaplicada sem derrubar a fila caso a coluna
     * já exista.
     */
    public function up(): void
    {
        if (Schema::hasColumn('clinics', 'whatsapp_status')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->string('whatsapp_status', 30)->nullable()->after('evolution_token');
            $table->timestamp('whatsapp_status_at')->nullable()->after('whatsapp_status');
            $table->text('whatsapp_last_error')->nullable()->after('whatsapp_status_at');
            $table->timestamp('whatsapp_provisioned_at')->nullable()->after('whatsapp_last_error');
            $table->timestamp('whatsapp_webhook_configured_at')->nullable()->after('whatsapp_provisioned_at');
            $table->timestamp('whatsapp_connected_at')->nullable()->after('whatsapp_webhook_configured_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clinics', 'whatsapp_status')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_status',
                'whatsapp_status_at',
                'whatsapp_last_error',
                'whatsapp_provisioned_at',
                'whatsapp_webhook_configured_at',
                'whatsapp_connected_at',
            ]);
        });
    }
};
