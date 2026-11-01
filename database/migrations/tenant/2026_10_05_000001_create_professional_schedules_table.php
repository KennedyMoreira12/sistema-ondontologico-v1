<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horários de atendimento por profissional: dia da semana e intervalo.
 * Um profissional pode ter vários intervalos no mesmo dia (ex.: 08-12 e 14-18).
 * Sem esta tabela a disponibilidade seria adivinhada, e o bot ofereceria
 * horários em que o profissional NAO atende.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professional_id')->index();
            $table->unsignedTinyInteger('weekday')->index(); // 0=domingo ... 6=sábado
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_schedules');
    }
};
