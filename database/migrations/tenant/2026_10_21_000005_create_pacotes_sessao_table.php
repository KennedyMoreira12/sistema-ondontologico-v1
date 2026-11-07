<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pacote de sessões de fisioterapia.
 *
 * O saldo vive em `sessoes_usadas`, e o consumo acontece em transação com
 * bloqueio de linha: duas atendentes marcando sessão ao mesmo tempo não podem
 * levar o pacote abaixo de zero. O CHECK do banco é a última rede, e é
 * aplicado onde o servidor aceita — o SQLite (usado nos testes) não aceita
 * CHECK em tabela já criada, e é justamente a atualização atômica do saldo
 * que garante o limite nos dois bancos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacotes_sessao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profissional_id')->nullable()->constrained('professionals')->nullOnDelete();

            $table->string('nome');
            $table->unsignedInteger('total_sessoes');
            $table->unsignedInteger('sessoes_usadas')->default(0);
            $table->decimal('valor_sessao', 10, 2)->default(0);
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->string('status')->default('ativo');
            $table->date('validade_em')->nullable();

            $table->timestamps();

            $table->index(['paciente_id', 'status']);
        });

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement(
                'ALTER TABLE pacotes_sessao ADD CONSTRAINT pacotes_sessao_saldo_check CHECK (sessoes_usadas >= 0 AND sessoes_usadas <= total_sessoes)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pacotes_sessao');
    }
};
