<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turmas de Pilates.
 *
 * A planilha PILATES/CALENDÁRIO PILATES é uma grade semanal de turmas: cada
 * célula tem o horário, o profissional e a lista de alunos. O sistema tinha
 * apenas pacotes por paciente, o que não representa a turma em si — quem é
 * aluno de qual turma, em qual dia e horário, e quem compareceu em cada data.
 *
 * `turma_pacientes` é o vínculo do aluno com a turma (o aluno pode sair da
 * turma sem apagar o histórico). `turma_presencas` é a lista de presença por
 * data, uma linha por aluno, e é o que permite reproduzir a marcação de
 * presença/falta da planilha sem sobrescrever as datas anteriores.
 *
 * Nenhuma regra de reposição, validade de pacote ou cobrança é fixada aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turmas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('servico', 40)->default('PILATES');
            $table->foreignId('profissional_id')->nullable()->constrained('professionals')->nullOnDelete();
            $table->unsignedTinyInteger('dia_semana')->index();
            $table->time('hora_inicio');
            $table->time('hora_fim')->nullable();
            $table->unsignedSmallInteger('capacidade')->nullable();
            $table->string('sala', 80)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['dia_semana', 'hora_inicio']);
        });

        Schema::create('turma_pacientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['turma_id', 'paciente_id']);
        });

        Schema::create('turma_presencas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->date('data');
            $table->string('status', 20)->default('presente');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['turma_id', 'paciente_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turma_presencas');
        Schema::dropIfExists('turma_pacientes');
        Schema::dropIfExists('turmas');
    }
};
