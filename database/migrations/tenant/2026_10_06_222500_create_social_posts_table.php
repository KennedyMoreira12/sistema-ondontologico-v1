<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rascunhos e registros de publicação no Facebook/Instagram da clínica.
 *
 * Vive no banco da clínica. `platforms` define o destino pretendido: Facebook,
 * Instagram ou ambos. `status` é o estado geral no fluxo (pendente/aprovado/
 * rejeitado), enquanto `facebook_status` e `instagram_status` acompanham cada
 * plataforma sem perdio uma decisão da outra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('tema', 255);
            $table->string('objetivo', 255)->nullable();
            $table->string('publico', 191)->nullable();
            $table->string('tipo', 60)->nullable();
            $table->text('observacoes')->nullable();
            $table->string('titulo', 255)->nullable();
            $table->text('legenda_instagram')->nullable();
            $table->text('legenda_facebook')->nullable();
            $table->string('cta', 255)->nullable();
            $table->json('hashtags')->nullable();
            $table->text('prompt_imagem')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->json('platforms');
            $table->string('status', 40)->default('pendente');
            $table->string('facebook_status', 40)->default('pendente');
            $table->string('instagram_status', 40)->default('pendente');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('facebook_post_id', 191)->nullable();
            $table->string('instagram_media_id', 191)->nullable();
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('status', 'social_posts_status_index');
            $table->index('scheduled_at', 'social_posts_scheduled_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
