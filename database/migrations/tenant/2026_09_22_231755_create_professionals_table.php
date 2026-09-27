<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professionals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinica_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('nome');
            $table->string('cpf', 14)->nullable();
            $table->string('cro', 30)->nullable();
            $table->string('tipo')->default('dentista');
            $table->decimal('comissao', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};
