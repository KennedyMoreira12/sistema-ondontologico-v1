<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contas a pagar e categorias do financeiro.
 *
 * A planilha CONTAS tem uma linha por conta do mês (aluguel, energia, cartão,
 * empréstimo...). `suppliers` sozinho não representava isso: fornecedor é
 * quem recebe, conta é o compromisso com vencimento e baixa.
 *
 * A baixa fica em `accounts_payable_pagamentos`, uma linha por pagamento, para
 * que uma conta possa ser paga em partes sem sobrescrever o valor original.
 * O valor pago é a soma dessas linhas — não há coluna duplicada que possa
 * divergir do extrato.
 *
 * `financeiro_categorias` é uma tabela de apoio, com as categorias genéricas
 * que a operação já usava. A cliente pode renomear/inativar sem perder o
 * histórico, e nenhuma regra comercial de juros/multa é fixada aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financeiro_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('despesa')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['nome', 'tipo']);
        });

        Schema::create('accounts_payable', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->unsignedBigInteger('financeiro_categoria_id')->nullable()->index();
            $table->string('nome_conta');
            $table->text('descricao')->nullable();
            $table->decimal('valor', 12, 2)->default(0);
            $table->date('data_vencimento')->index();
            $table->date('data_pagamento')->nullable();
            $table->string('status', 20)->default('pendente')->index();
            $table->string('tipo', 30)->default('outros')->index();
            $table->string('tipo_documento', 20)->default('outros');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('accounts_payable_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounts_payable_id')->constrained('accounts_payable')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->date('data');
            $table->decimal('valor', 12, 2);
            $table->string('observacoes')->nullable();
            $table->timestamps();

            $table->index(['accounts_payable_id', 'data']);
        });

        $agora = now();

        $categorias = [
            ['nome' => 'Aluguel', 'tipo' => 'despesa'],
            ['nome' => 'Energia', 'tipo' => 'despesa'],
            ['nome' => 'Água', 'tipo' => 'despesa'],
            ['nome' => 'Telefone e internet', 'tipo' => 'despesa'],
            ['nome' => 'Materiais e insumos', 'tipo' => 'despesa'],
            ['nome' => 'Folha de pagamento', 'tipo' => 'despesa'],
            ['nome' => 'Impostos e taxas', 'tipo' => 'despesa'],
            ['nome' => 'Empréstimo', 'tipo' => 'despesa'],
            ['nome' => 'Serviços', 'tipo' => 'despesa'],
            ['nome' => 'Outros', 'tipo' => 'despesa'],
            ['nome' => 'Atendimentos', 'tipo' => 'receita'],
            ['nome' => 'Pacotes de sessões', 'tipo' => 'receita'],
            ['nome' => 'Produtos', 'tipo' => 'receita'],
            ['nome' => 'Outros', 'tipo' => 'receita'],
        ];

        DB::table('financeiro_categorias')->insertOrIgnore(array_map(
            fn (array $categoria): array => $categoria + [
                'is_active' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ],
            $categorias,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_payable_pagamentos');
        Schema::dropIfExists('accounts_payable');
        Schema::dropIfExists('financeiro_categorias');
    }
};
