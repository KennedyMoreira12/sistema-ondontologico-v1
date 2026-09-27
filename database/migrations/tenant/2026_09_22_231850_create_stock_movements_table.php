<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('tipo');
            $table->decimal('quantidade', 12, 3);
            $table->decimal('saldo_anterior', 12, 3)->default(0);
            $table->decimal('saldo_posterior', 12, 3)->default(0);
            $table->decimal('custo', 12, 2)->default(0);
            $table->string('motivo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
