<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags de recurso por clínica, no banco CENTRAL.
 *
 * Ficam aqui, e não no banco da clínica, porque são configuração/ativação da
 * plataforma: o operador precisa enxergar e mudar sem abrir o banco de
 * ninguém. O dado operacional (mensagem, conversa, outbox) continua no banco
 * da clínica.
 *
 * A ausência de linha significa "usar o padrão de config/whatsapp.php", que é
 * desligado. Não há backfill: toda clínica existente começa com os recursos
 * novos desligados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('flag', 60);
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->unique(['clinic_id', 'flag'], 'clinic_feature_flags_unique');
            $table->index(['flag', 'enabled'], 'clinic_feature_flags_flag_enabled_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_feature_flags');
    }
};
