<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado atual do WhatsApp, separado do histórico da última conexão.
     *
     * O bug corrigido aqui é o de uma falha transitória de rede manter
     * `whatsapp_connected_at` antigo e `pronto=true`. A data antiga continua
     * existindo como histórico, mas deixa de ser prova de conexão: quem prova o
     * estado atual é `whatsapp_current_state`, gravado só quando a Evolution
     * confirma (ou quando uma mensagem autenticada chega).
     *
     * IDEMPOTENTE coluna a coluna, pelo mesmo motivo da migration anterior:
     * um ALTER interrompido no MariaDB pode deixar parte das colunas criadas.
     */
    public function up(): void
    {
        foreach ($this->colunas() as $coluna) {
            if (Schema::hasColumn('clinics', $coluna)) {
                continue;
            }

            Schema::table('clinics', function (Blueprint $table) use ($coluna): void {
                match ($coluna) {
                    'whatsapp_last_connected_at' => $table->timestamp($coluna)->nullable()->after('whatsapp_connected_at'),
                    'whatsapp_current_state' => $table->string($coluna, 30)->nullable()->after('whatsapp_last_connected_at'),
                    'whatsapp_current_state_at' => $table->timestamp($coluna)->nullable()->after('whatsapp_current_state'),
                    'whatsapp_legacy_adotado_at' => $table->timestamp($coluna)->nullable()->after('whatsapp_current_state_at'),
                };
            });
        }

        /**
         * Clínicas antigas já têm `whatsapp_connected_at`, mas não têm histórico
         * separado. Copiar a data existente evita que a primeira falha de rede
         * apague a única prova de conexão que o banco tinha.
         */
        if (Schema::hasColumn('clinics', 'whatsapp_connected_at')) {
            DB::table('clinics')
                ->whereNotNull('whatsapp_connected_at')
                ->whereNull('whatsapp_last_connected_at')
                ->update(['whatsapp_last_connected_at' => DB::raw('whatsapp_connected_at')]);
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
     * @return list<string>
     */
    private function colunas(): array
    {
        return [
            'whatsapp_last_connected_at',
            'whatsapp_current_state',
            'whatsapp_current_state_at',
            'whatsapp_legacy_adotado_at',
        ];
    }
};
