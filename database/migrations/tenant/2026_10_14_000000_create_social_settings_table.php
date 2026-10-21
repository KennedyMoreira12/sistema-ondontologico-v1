<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciais de Instagram e Facebook DA CLÍNICA.
 *
 * Linha única (o serviço sempre usa a primeira), separada dos posts de
 * propósito. O `page_access_token` é gravado cifrado pelo cast `encrypted` do
 * model — nunca em claro no banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_settings', function (Blueprint $table) {
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
        Schema::dropIfExists('social_settings');
    }
};
