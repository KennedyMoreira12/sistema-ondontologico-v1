<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained('clinics')->cascadeOnDelete();

            $table->string('driver')->default('mysql');
            $table->string('database_name');

            /**
             * Servidor e credenciais só têm sentido no MySQL. Num tenant sqlite
             * (desenvolvimento e testes) essas colunas ficam nulas de propósito,
             * por isso são nullable — transformá-las em NOT NULL impediria criar
             * clínica em sqlite.
             */
            $table->string('database_host')->nullable();
            $table->unsignedInteger('database_port')->nullable();
            $table->string('database_username')->nullable();

            /**
             * Criptografado pelo cast `encrypted` do model. Nunca exibir em
             * tela, nunca registrar em log.
             */
            $table->text('database_password')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_migrated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_connections');
    }
};
