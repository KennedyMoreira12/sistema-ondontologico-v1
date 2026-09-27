<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name');

            /**
             * SHA-256 do token. O valor em claro existe uma única vez, na
             * resposta da criação — nunca é guardado, nunca é exibido de novo.
             */
            $table->string('token_hash', 64)->unique();

            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_tokens');
    }
};
