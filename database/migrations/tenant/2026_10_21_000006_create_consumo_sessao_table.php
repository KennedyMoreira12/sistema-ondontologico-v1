<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consumo de sessão de um pacote.
 *
 * Cada linha é uma sessão consumida: é o extrato do pacote e a origem do
 * débito. A linha é criada na MESMA transação que incrementa o saldo do
 * pacote, então não existe consumo sem saldo debitado — nem saldo debitado
 * sem consumo registrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consumo_sessao')) {
            return;
        }

        Schema::create('consumo_sessao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pacote_id')->constrained('pacotes_sessao')->cascadeOnDelete();
            $table->foreignId('paciente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profissional_id')->nullable()->constrained('professionals')->nullOnDelete();
            $table->foreignId('evolucao_sessao_id')->nullable()->constrained('evolucoes_sessao')->nullOnDelete();

            $table->date('data');
            $table->decimal('valor', 10, 2)->default(0);
            $table->text('observacoes')->nullable();

            $table->timestamps();

            $table->index(['pacote_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumo_sessao');
    }
};
