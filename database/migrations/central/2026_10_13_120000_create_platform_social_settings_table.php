<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas de Instagram e Facebook DA PLATAFORMA (a LJ).
 *
 * Tabela de linha única, como `prospect_whatsapp_settings`: não pertence a
 * clínica nenhuma. O `page_access_token` é cifrado pelo cast `encrypted` do
 * model, então o dump do banco central sozinho não vira credencial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_social_settings', function (Blueprint $table) {
            $table->id();
            $table->string('facebook_page_id', 191)->nullable();
            $table->string('facebook_page_name', 191)->nullable();
            $table->string('instagram_business_id', 191)->nullable();
            $table->string('instagram_username', 191)->nullable();
            $table->text('page_access_token')->nullable();
            $table->string('graph_version', 20)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_social_settings');
    }
};
