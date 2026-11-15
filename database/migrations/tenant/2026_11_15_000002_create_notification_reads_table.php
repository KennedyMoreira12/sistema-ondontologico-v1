<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de leitura de cada usuário sobre as notificações da clínica.
 *
 * `user_id` aponta para `users` do banco CENTRAL, então não há FK — a mesma
 * situação de `user_roles`. A integridade é garantida pelo fluxo, que só
 * escreve aqui com o usuário autenticado no banco da clínica dele.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at');
            $table->timestamps();

            // Uma linha por usuário e notificação: marcar duas vezes não duplica.
            $table->unique(['notification_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
