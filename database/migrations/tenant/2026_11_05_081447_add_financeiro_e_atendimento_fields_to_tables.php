<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos que faltavam para a operação da clínica da Daiane.
 *
 * `payments` guardava valor, data e observações, mas não dizia de QUEM era o
 * pagamento nem qual serviço ele quitou — por isso os lançamentos importados
 * ficaram sem vínculo com o paciente. As colunas novas são anuláveis para não
 * quebrar o histórico já importado: o vínculo é preenchido depois, por revisão
 * humana (nunca por merge automático de nomes).
 *
 * Em `appointments`, `area` e `servico` trazem para a agenda o que a planilha
 * de laser já tinha (AXILA, VIRILHA, ...). `valor` é o previsto do atendimento,
 * separado do pagamento efetivo, que continua em `payments`.
 *
 * Em `consumo_sessao`, `appointment_id` liga a sessão debitada ao atendimento
 * que a originou. É o que permite provar que uma falta não consumiu sessão e
 * que o mesmo atendimento não debita o pacote duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('paciente_id')->nullable()->index();
            $table->string('servico', 120)->nullable()->index();
            $table->string('origem', 30)->nullable();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('tipo_documento', 20)->default('outros');
            $table->text('observacoes')->nullable();
        });

        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->string('descricao')->nullable();
            $table->string('servico', 120)->nullable();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('area', 80)->nullable();
            $table->string('servico', 120)->nullable()->index();
            $table->decimal('valor', 12, 2)->nullable();
        });

        Schema::table('pacotes_sessao', function (Blueprint $table) {
            $table->string('servico', 120)->nullable()->index();
        });

        Schema::table('consumo_sessao', function (Blueprint $table) {
            $table->unsignedBigInteger('appointment_id')->nullable()->unique();
            $table->boolean('reposicao')->default(false);
        });
    }

    public function down(): void
    {
        // No SQLite, uma coluna que participa de índice não pode ser removida
        // antes do índice. Os índices criados na subida são removidos aqui de
        // forma explícita, na mesma ordem em que deixaram de ser necessários.
        Schema::table('consumo_sessao', function (Blueprint $table) {
            $table->dropUnique(['appointment_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paciente_id']);
            $table->dropIndex(['servico']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['servico']);
        });

        Schema::table('pacotes_sessao', function (Blueprint $table) {
            $table->dropIndex(['servico']);
        });

        Schema::table('consumo_sessao', function (Blueprint $table) {
            $table->dropColumn(['appointment_id', 'reposicao']);
        });

        Schema::table('pacotes_sessao', function (Blueprint $table) {
            $table->dropColumn('servico');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['area', 'servico', 'valor']);
        });

        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->dropColumn(['descricao', 'servico']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['tipo_documento', 'observacoes']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['paciente_id', 'servico', 'origem']);
        });
    }
};
