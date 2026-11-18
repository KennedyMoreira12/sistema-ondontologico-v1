<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Webhook do n8n POR CLÍNICA.
     *
     * O `N8N_WEBHOOK_URL` do .env é único para a instalação inteira, e o
     * workflow por trás dele carrega UMA credencial de integração. Isso serve
     * enquanto só uma clínica usa o atendimento automático; com duas, a
     * mensagem da segunda seria respondida pela credencial da primeira — ou
     * seja, no banco e no WhatsApp da clínica errada.
     *
     * Com a coluna, cada clínica aponta para o workflow dela, que carrega o
     * token dela. Nula significa "usar o webhook global", que é o estado de
     * quem nunca precisou de um workflow próprio.
     *
     * IDEMPOTENTE para poder ser reaplicada sem derrubar a fila caso a coluna
     * já exista.
     */
    public function up(): void
    {
        if (Schema::hasColumn('clinics', 'n8n_webhook_url')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->string('n8n_webhook_url', 500)->nullable()->after('evolution_token');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clinics', 'n8n_webhook_url')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('n8n_webhook_url');
        });
    }
};
