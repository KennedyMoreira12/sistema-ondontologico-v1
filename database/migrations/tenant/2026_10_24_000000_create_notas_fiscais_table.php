<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paciente_id')->nullable();
            $table->unsignedBigInteger('consulta_id')->nullable();
            $table->string('numero', 30)->nullable();
            $table->string('serie', 10)->nullable();
            $table->string('chave', 50)->nullable()->unique();
            $table->string('status', 20)->default('rascunho'); // rascunho|autorizada|cancelada|rejeitada
            $table->string('tipo', 10)->default('nfe'); // nfe|nfce
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->decimal('valor_icms', 10, 2)->default(0);
            $table->json('dados')->nullable();
            $table->timestamp('emitida_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->string('link_pdf')->nullable();
            $table->string('link_xml')->nullable();
            $table->timestamps();

            $table->index(['status', 'emitida_em']);
            $table->index('paciente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_fiscais');
    }
};
