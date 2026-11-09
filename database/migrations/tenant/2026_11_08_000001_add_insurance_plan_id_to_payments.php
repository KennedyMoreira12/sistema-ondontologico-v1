<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo de pagamento com plano de saúde.
 *
 * A planilha PAGAMENTOS traz, em alguns registros, o valor com plano de
 * saúde (copagamento) e o nome da operadora (ex: IPSM). O campo `servico`
 * de `payments` não é suficiente para vincular o pagamento a um plano
 * estruturado. Esta migration adiciona `insurance_plan_id`, anulável para
 * não quebrar os 6.163 lançamentos já importados sem esse vínculo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('insurance_plan_id')->nullable()
                ->after('servico')
                ->index();

            $table->foreign('insurance_plan_id')
                ->references('id')
                ->on('insurance_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['insurance_plan_id']);
            $table->dropIndex(['insurance_plan_id']);
            $table->dropColumn('insurance_plan_id');
        });
    }
};
