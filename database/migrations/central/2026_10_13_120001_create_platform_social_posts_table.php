<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posts das redes sociais DA PLATAFORMA (a LJ).
 *
 * Vive no banco central, diferente de `social_posts`, que é de cada clínica.
 * `platforms` diz o destino (facebook/instagram); `facebook_status` e
 * `instagram_status` acompanham cada rede sem perder a decisão da outra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_social_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->string('titulo', 255);
            $table->text('legenda_instagram')->nullable();
            $table->text('legenda_facebook')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->string('image_path', 1000)->nullable();
            $table->json('platforms')->nullable();
            $table->string('status', 40)->default('rascunho');
            $table->string('facebook_status', 40)->default('pendente');
            $table->string('instagram_status', 40)->default('pendente');
            $table->string('facebook_post_id', 191)->nullable();
            $table->string('instagram_media_id', 191)->nullable();
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('status', 'platform_social_posts_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_social_posts');
    }
};
