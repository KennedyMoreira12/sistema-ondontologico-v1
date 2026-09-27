<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            /**
             * `user_id` aponta para a tabela `users` do BANCO CENTRAL e
             * `role_id` para a tabela `roles` DESTE banco. Não é possível criar
             * uma FK cross-database, então `user_id` fica sem constraint — a
             * integridade é garantida pelo TenantManager, que só resolve um
             * usuário depois de conectar no banco da clínica dele.
             */
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
