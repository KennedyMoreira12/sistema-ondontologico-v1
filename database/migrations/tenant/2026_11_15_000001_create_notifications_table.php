<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central de notificações da clínica.
 *
 * A linha é o FATO que interessa à clínica inteira (conta vencida, estoque
 * zerado, sessão próxima). O estado de leitura é por usuário e vive em
 * `notification_reads` — assim o alerta é criado uma vez só e cada pessoa
 * marca o seu.
 *
 * `dedupe_key` é única porque a varredura roda de tempos em tempos e a mesma
 * condição continua verdadeira: sem a chave, cada execução criaria uma cópia
 * do mesmo aviso. A varredura faz `updateOrCreate` por ela.
 *
 * `resolved_at` fecha o alerta quando a condição deixa de existir (a conta foi
 * paga, o estoque foi reposto). O histórico continua visível; o que muda é que
 * ele sai da lista de pendentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // financeiro | estoque | agenda | pacientes | assinaturas
            $table->string('categoria', 40);

            // identificador do fato, ex.: conta_pagar_vencida, estoque_zerado
            $table->string('tipo', 80);

            // info | warning | critical — decide o som e o destaque visual
            $table->string('prioridade', 20)->default('info');

            $table->string('titulo', 180);
            $table->text('mensagem')->nullable();

            // link relativo para o registro relacionado (/financeiro/contas-pagar/12)
            $table->string('url', 255)->nullable();

            // permissão necessária para ver o alerta (null = todos veem)
            $table->string('permissao', 80)->nullable();

            // vínculo opcional com o registro de origem
            $table->string('referencia_type', 80)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();

            // controle de duplicidade da varredura e dos eventos
            $table->string('dedupe_key', 191)->unique();

            // varredura (condição reavaliada) | evento (fato pontual)
            $table->string('origem', 20)->default('varredura');

            $table->timestamp('expira_em')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['categoria', 'resolved_at']);
            $table->index(['prioridade', 'resolved_at']);
            $table->index(['origem', 'categoria', 'resolved_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
