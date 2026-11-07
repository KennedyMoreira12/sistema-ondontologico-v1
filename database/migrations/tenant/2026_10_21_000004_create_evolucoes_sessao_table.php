<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evolução por sessão de fisioterapia.
 *
 * Cada sessão registrada tem o que foi feito, a resposta do paciente e a
 * assinatura do profissional. A assinatura (`status` = 'finalizada') deixa o
 * registro imutável: é o que dá peso à auditoria.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evolucoes_sessao')) {
            return;
        }

        Schema::create('evolucoes_sessao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profissional_id')->nullable()->constrained('professionals')->nullOnDelete();
            $table->foreignId('avaliacao_id')->nullable()->constrained('avaliacoes')->nullOnDelete();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();

            $table->date('data');

            $table->text('conteudo');
            $table->text('tecnicas')->nullable();
            $table->text('resposta_paciente')->nullable();

            $table->unsignedTinyInteger('dor_antes')->nullable();
            $table->unsignedTinyInteger('dor_depois')->nullable();

            $table->string('status')->default('rascunho');
            $table->timestamp('finalizada_em')->nullable();

            $table->timestamps();

            $table->index(['paciente_id', 'data']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evolucoes_sessao');
    }
};
