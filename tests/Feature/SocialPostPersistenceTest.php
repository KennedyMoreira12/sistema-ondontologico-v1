<?php

namespace Tests\Feature;

use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class SocialPostPersistenceTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_tenant_schema_has_social_posts_table(): void
    {
        $this->assertTrue(
            Schema::connection('tenant')->hasTable('social_posts'),
            'A migration de social_posts deveria criar a tabela no schema tenant.'
        );
    }

    public function test_create_retrive_and_casts_social_post(): void
    {
        $this->criarClinicaTeste();

        $post = SocialPost::create([
            'tema' => 'Clareamento dental',
            'objetivo' => 'Divulgação',
            'publico' => 'Pacientes da clínica',
            'tipo' => 'Post',
            'observacoes' => 'Tom profissional, sem prometer resultado.',
            'titulo' => 'Clareamento com segurança',
            'legenda_instagram' => 'Volte a sorrir com segurança. Agende uma avaliação.',
            'legenda_facebook' => 'Clareamento dental com acompanhamento profissional.',
            'cta' => 'Agende avaliação',
            'hashtags' => ['#odontologia', '#clareamento'],
            'prompt_imagem' => 'Foto profissional de consultório, sem pessoas reais.',
            'image_url' => 'https://example.com/social.jpg',
            'platforms' => ['facebook', 'instagram'],
            'status' => 'pendente',
            'facebook_status' => 'pendente',
            'instagram_status' => 'pendente',
            'scheduled_at' => now()->addDay(),
            'payload' => ['source' => 'test'],
        ]);

        $this->assertDatabaseHas('social_posts', ['id' => $post->id], 'tenant');
        $this->assertSame(['#odontologia', '#clareamento'], $post->hashtags);
        $this->assertSame(['facebook', 'instagram'], $post->platforms);
        $this->assertSame(['source' => 'test'], $post->payload);
        $this->assertSame('pendente', $post->status);
        $this->assertSame('pendente', $post->facebook_status);
        $this->assertSame('pendente', $post->instagram_status);
    }

    public function test_update_status_per_platform(): void
    {
        $this->criarClinicaTeste();

        $post = SocialPost::create([
            'tema' => 'Consulta',
            'objetivo' => 'Attração',
            'platforms' => ['facebook'],
        ]);

        $post->update([
            'status' => 'aprovado',
            'facebook_status' => 'publicado',
            'published_at' => now(),
            'facebook_post_id' => '12345',
        ]);

        $this->assertDatabaseHas('social_posts', [
            'id' => $post->id,
            'status' => 'aprovado',
            'facebook_status' => 'publicado',
            'instagram_status' => 'pendente',
            'facebook_post_id' => '12345',
        ], 'tenant');
    }
}
