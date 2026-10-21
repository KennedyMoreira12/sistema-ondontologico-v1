<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('instance_name')->unique();
            $table->string('instance_uuid')->nullable();

            /**
             * Token da instância de prospecção. Cifrado com a APP_KEY pelo cast
             * `encrypted` do model, como `clinics.evolution_token`: o dump do
             * banco central sozinho não vira credencial.
             */
            $table->text('instance_token')->nullable();

            $table->string('status', 20)->default('disconnected');
            $table->string('last_state', 30)->nullable();
            $table->dateTime('last_state_at')->nullable();
            $table->dateTime('connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_whatsapp_settings');
    }
};
