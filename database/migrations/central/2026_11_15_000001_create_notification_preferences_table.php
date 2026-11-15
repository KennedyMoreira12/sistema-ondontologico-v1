<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferências de notificação por usuário.
 *
 * Ficam no banco central porque o usuário também vive lá: a preferência de som
 * acompanha a pessoa, não a clínica. O volume e as categorias são do navegador
 * que toca o som; o servidor só guarda a escolha para não voltar ao padrão a
 * cada login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Interruptor geral dos sons.
            $table->boolean('sons_ativos')->default(true);

            // Volume em porcentagem (0 a 100).
            $table->unsignedTinyInteger('volume')->default(60);

            /**
             * Liga/desliga por categoria:
             * {"financeiro": true, "estoque": true, "agenda": true,
             *  "pacientes": true, "assinaturas": true}
             *
             * Nulo significa "todas ligadas" — assim uma coluna nova de
             * categoria não chega desligada em quem nunca abriu a tela.
             */
            $table->json('categorias')->nullable();

            // Permissão do navegador para notificações nativas (Notifications API).
            $table->boolean('navegador_ativo')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
