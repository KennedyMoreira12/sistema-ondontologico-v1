<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha de avaliação inicial (fisioterapia).
 *
 * Tabela NOVA, que não toca em nada de odontologia. A ficha é preenchida pelo
 * profissional: o sistema não sugere diagnóstico, não calcula score e não
 * inventa conduta — os campos de texto são livres e quem preenche é o
 * profissional responsável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profissional_id')->nullable()->constrained('professionals')->nullOnDelete();

            $table->date('data');

            $table->text('queixa_principal');
            $table->text('historia_clinica')->nullable();
            $table->text('exame_fisico')->nullable();
            $table->text('hipotese_clinica')->nullable();
            $table->text('objetivos')->nullable();
            $table->text('plano_terapeutico')->nullable();
            $table->text('observacoes')->nullable();

            /**
             * A ficha nasce em rascunho e só vira definitiva quando o
             * profissional finaliza. Rascunho é editável; definitiva não é —
             * a auditoria depende dessa imutabilidade.
             */
            $table->string('status')->default('rascunho');
            $table->timestamp('finalizada_em')->nullable();

            $table->timestamps();

            $table->index(['paciente_id', 'data']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes');
    }
};
