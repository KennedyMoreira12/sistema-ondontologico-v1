<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();

            /**
             * Primeiro dia do mês que está sendo medido, em YYYY-MM-01.
             */
            $table->date('period');

            $table->unsignedBigInteger('messages_inbound')->default(0);
            $table->unsignedBigInteger('messages_outbound')->default(0);
            $table->unsignedBigInteger('ai_requests')->default(0);
            $table->unsignedBigInteger('ai_input_tokens')->default(0);
            $table->unsignedBigInteger('ai_output_tokens')->default(0);
            $table->unsignedBigInteger('posts_published')->default(0);
            $table->unsignedBigInteger('n8n_executions')->default(0);
            $table->unsignedBigInteger('storage_bytes')->default(0);

            $table->timestamps();

            $table->unique(['clinic_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_metrics');
    }
};
