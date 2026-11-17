<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regiões a laser: catálogo de regiões corporais, pacotes-modelo por região e
 * histórico de aplicações por paciente.
 *
 * O pacote aqui é MODELO, não pacote contratado: ele diz quantas sessões, o
 * valor e a validade de um formato de venda. Quem registra a aplicação é o
 * histórico, e nenhuma cobrança é gerada automaticamente — o financeiro
 * continua sendo lançado à mão, como no restante da clínica.
 *
 * Histórico clínico não se apaga em cascata: apagar uma região ou um pacote
 * que já tem aplicações é recusado pela FK e pela tela. O paciente usa soft
 * delete, então a aplicação continua ligada a ele mesmo depois da exclusão
 * lógica.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('laser_regioes')) {
            Schema::create('laser_regioes', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 120)->unique();
                $table->text('descricao')->nullable();
                $table->unsignedInteger('ordem')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
            });
        }

        if (! Schema::hasTable('laser_pacotes')) {
            Schema::create('laser_pacotes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('regiao_id')->constrained('laser_regioes')->restrictOnDelete();

                $table->string('nome', 120);
                $table->unsignedInteger('total_sessoes');
                $table->decimal('valor', 10, 2)->default(0);
                $table->unsignedInteger('validade_dias')->nullable();
                $table->text('descricao')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['regiao_id', 'nome']);
                $table->index(['regiao_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('laser_aplicacoes')) {
            Schema::create('laser_aplicacoes', function (Blueprint $table) {
                $table->id();

                /**
                 * Sem cascade: a aplicação é histórico clínico. O paciente usa
                 * soft delete, então o vínculo continua existindo.
                 */
                $table->foreignId('paciente_id')->constrained('pacientes');
                $table->foreignId('regiao_id')->constrained('laser_regioes')->restrictOnDelete();
                $table->foreignId('pacote_id')->nullable()->constrained('laser_pacotes')->restrictOnDelete();
                $table->foreignId('profissional_id')->nullable()->constrained('professionals')->nullOnDelete();

                $table->date('data');
                $table->unsignedInteger('numero_sessao')->default(1);
                $table->text('observacoes')->nullable();
                $table->timestamps();

                $table->index(['paciente_id', 'data']);
                $table->index(['regiao_id', 'data']);
                $table->index(['pacote_id', 'numero_sessao']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('laser_aplicacoes');
        Schema::dropIfExists('laser_pacotes');
        Schema::dropIfExists('laser_regioes');
    }
};
