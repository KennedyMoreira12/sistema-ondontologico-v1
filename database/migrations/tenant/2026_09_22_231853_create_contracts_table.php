<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained()->cascadeOnDelete();

            /**
             * `users` vive no banco central, então esta coluna é uma referência
             * sem FK: constraint cross-database não existe. Mesmo motivo de
             * `user_roles.user_id`.
             */
            $table->foreignId('user_id')->nullable()->index();
            $table->string('tipo');
            $table->text('conteudo')->nullable();
            $table->string('status')->default('pendente');
            $table->dateTime('assinado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
