<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nome');
            $table->string('codigo', 50)->nullable()->unique();
            $table->string('unidade', 20)->default('un');
            $table->decimal('estoque', 12, 3)->default(0);
            $table->decimal('estoque_minimo', 12, 3)->default(0);
            $table->decimal('custo', 12, 2)->default(0);
            $table->decimal('preco', 12, 2)->default(0);
            $table->date('validade')->nullable();
            $table->string('lote')->nullable();
            $table->boolean('controlar_estoque')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
