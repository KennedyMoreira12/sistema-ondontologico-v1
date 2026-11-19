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
     * IDEMPOTENTE COLUNA A COLUNA: uma aplicação interrompida no meio do ALTER
     * pode deixar parte das colunas criadas. Reaplicar completa as faltantes e o
     * rollback remove apenas as que existem, sem depender de um estado "todas ou
     * nenhuma" que o MariaDB não garante.
     */
    public function up(): void
    {
        foreach ($this->colunas() as $coluna) {
            if (Schema::hasColumn('clinics', $coluna)) {
                continue;
            }

            Schema::table('clinics', function (Blueprint $table) use ($coluna): void {
                match ($coluna) {
                    'whatsapp_status' => $table->string($coluna, 30)->nullable()->after('evolution_token'),
                    'whatsapp_status_at' => $table->timestamp($coluna)->nullable()->after('whatsapp_status'),
                    'whatsapp_last_error' => $table->text($coluna)->nullable()->after('whatsapp_status_at'),
                    'whatsapp_provisioned_at' => $table->timestamp($coluna)->nullable()->after('whatsapp_last_error'),
                    'whatsapp_webhook_configured_at' => $table->timestamp($coluna)->nullable()->after('whatsapp_provisioned_at'),
                    'whatsapp_connected_at' => $table->timestamp($coluna)->nullable()->after('whatsapp_webhook_configured_at'),
                };
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->colunas()) as $coluna) {
            if (! Schema::hasColumn('clinics', $coluna)) {
                continue;
            }

            Schema::table('clinics', function (Blueprint $table) use ($coluna): void {
                $table->dropColumn($coluna);
            });
        }
    }

    /**
     * Ordem importa: o `after()` de cada coluna aponta para a anterior.
     *
     * @return list<string>
     */
    private function colunas(): array
    {
        return [
            'whatsapp_status',
            'whatsapp_status_at',
            'whatsapp_last_error',
            'whatsapp_provisioned_at',
            'whatsapp_webhook_configured_at',
            'whatsapp_connected_at',
        ];
    }
};
