<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo da conta a pagar com o profissional externo e encargos informados.
 *
 * A planilha PROFISSIONAIS EXTERNOS paga nutricionista por diária e dentista
 * por porcentagem. O pagamento é dinheiro que SAI: por isso ele pertence a
 * `accounts_payable`, e não a `accounts_receivable` (que representa dinheiro
 * entrando do paciente). `professional_id` liga a conta ao profissional e
 * `tipo_remuneracao` registra o modelo combinado, sem calcular o valor por
 * conta própria.
 *
 * `juros` e `multa` na baixa são valores INFORMADOS por quem paga. Nenhuma
 * taxa é fixada aqui: a cliente ainda não confirmou a regra comercial, e o
 * sistema não deve inventá-la. O valor principal continua em `valor`, que é o
 * que abate o saldo da conta; juros e multa ficam registrados ao lado para o
 * extrato e o total desembolsado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_payable', function (Blueprint $table) {
            $table->unsignedBigInteger('professional_id')->nullable()->index()->after('supplier_id');
            $table->string('tipo_remuneracao', 20)->nullable()->after('professional_id');
        });

        Schema::table('accounts_payable_pagamentos', function (Blueprint $table) {
            $table->decimal('juros', 12, 2)->default(0)->after('valor');
            $table->decimal('multa', 12, 2)->default(0)->after('juros');
        });
    }

    public function down(): void
    {
        Schema::table('accounts_payable_pagamentos', function (Blueprint $table) {
            $table->dropColumn(['juros', 'multa']);
        });

        Schema::table('accounts_payable', function (Blueprint $table) {
            // O índice precisa sair antes da coluna: no SQLite, remover a coluna
            // com índice pendente quebra o rollback.
            $table->dropIndex(['professional_id']);
            $table->dropColumn(['professional_id', 'tipo_remuneracao']);
        });
    }
};
