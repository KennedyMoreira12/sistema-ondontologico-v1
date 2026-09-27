<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('feature');

            /**
             * Limites do recurso, quando o recurso não for apenas um booleano.
             * Exemplo: {"posts_per_month": 50, "ai_messages_per_month": 2000}.
             * Nulo significa que o recurso está ligado sem contagem.
             */
            $table->json('limits')->nullable();

            $table->timestamps();

            $table->unique(['plan_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
