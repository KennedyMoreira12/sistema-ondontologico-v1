<?php

namespace Tests\Feature;

use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SocialPostApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->criarClinica('Clínica Social');

        $this->token = $ctx['token'];
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function criarPost(array $data = []): SocialPost
    {
        return SocialPost::create(array_merge([
            'tema' => 'Clareamento dental',
            'objetivo' => 'Divulgação',
            'publico' => 'Pacientes',
            'tipo' => 'post',
            'platforms' => ['facebook', 'instagram'],
        ], $data));
    }

    public function test_create_starts_pending_and_casts_json(): void
    {
        $payload = [
            'tema' => 'Clareamento dental',
            'objetivo' => 'Divulgação',
            'publico' => 'Pacientes da clínica',
            'tipo' => 'post',
            'observacoes' => 'Tom profissional',
            'titulo' => 'Clareamento',
            'legenda_instagram' => 'Legenda IG',
            'legenda_facebook' => 'Legenda FB',
            'cta' => 'Saiba mais',
            'hashtags' => ['#odontologia', '#sorriso'],
            'prompt_imagem' => 'prompt img',
            'image_url' => 'https://example.com/img.jpg',
            'platforms' => ['facebook', 'instagram'],
            'scheduled_at' => now()->addDay()->toISOString(),
        ];

        $this->postJson('/api/social/posts', $payload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('post.status', 'pendente')
            ->assertJsonPath('post.facebook_status', 'pendente')
            ->assertJsonPath('post.instagram_status', 'pendente');

        $post = SocialPost::first();

        $this->assertSame('pendente', $post->status);
        $this->assertSame(['facebook', 'instagram'], $post->platforms);
        $this->assertSame(['#odontologia', '#sorriso'], $post->hashtags);
        $this->assertNull($post->approved_at);
        $this->assertNull($post->published_at);
    }

    public function test_create_ignores_server_only_fields(): void
    {
        $payload = [
            'tema' => 'Clareamento',
            'platforms' => ['facebook'],
            'status' => 'aprovado',
            'facebook_status' => 'publicado',
            'instagram_status' => 'publicado',
            'approved_at' => now()->toISOString(),
            'published_at' => now()->toISOString(),
            'facebook_post_id' => '999',
            'instagram_media_id' => '999',
            'error' => 'hack',
        ];

        $this->postJson('/api/social/posts', $payload, $this->headers())->assertCreated();

        $post = SocialPost::first();

        $this->assertSame('pendente', $post->status);
        $this->assertSame('pendente', $post->facebook_status);
        $this->assertSame('pendente', $post->instagram_status);
        $this->assertNull($post->approved_at);
        $this->assertNull($post->published_at);
        $this->assertNull($post->facebook_post_id);
        $this->assertNull($post->instagram_media_id);
        $this->assertNull($post->error);
    }

    public function test_list_requires_authenticated_tenant_token(): void
    {
        $this->getJson('/api/social/posts')->assertUnauthorized();
    }

    public function test_can_list_and_filter_posts(): void
    {
        $this->criarPost(['platforms' => ['facebook']]);
        $this->criarPost(['platforms' => ['instagram']]);
        $this->criarPost(['platforms' => ['facebook', 'instagram']]);

        $this->getJson('/api/social/posts', $this->headers())
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/social/posts?platform=facebook', $this->headers())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_show_post(): void
    {
        $post = $this->criarPost();

        $this->getJson("/api/social/posts/{$post->id}", $this->headers())
            ->assertOk()
            ->assertJsonPath('post.id', $post->id);
    }

    public function test_cannot_show_post_from_other_clinic(): void
    {
        $ctxB = $this->criarClinica('Outra Clínica', 'outra@test.com');

        $postFromB = SocialPost::create([
            'tema' => 'Post B',
            'platforms' => ['facebook'],
        ]);

        // Caçamos com o token da clínica desta classe; o item está no banco
        // da clínica B, então o tenant correto devolve 404.
        $this->getJson("/api/social/posts/{$postFromB->id}", ['Authorization' => "Bearer {$this->token}"])
            ->assertNotFound();
    }

    public function test_can_approve_pending_post(): void
    {
        $post = $this->criarPost();

        $this->patchJson("/api/social/posts/{$post->id}/approve", [], $this->headers())
            ->assertOk()
            ->assertJsonPath('post.status', 'aprovado');

        $this->assertNotNull($post->fresh()->approved_at);
    }

    public function test_cannot_approve_post_that_is_already_rejected(): void
    {
        $post = SocialPost::create([
            'tema' => 'Já rejeitado',
            'platforms' => ['facebook'],
            'status' => 'rejeitado',
        ]);

        $this->patchJson("/api/social/posts/{$post->id}/approve", [], $this->headers())
            ->assertUnprocessable();
    }

    public function test_cannot_approve_nonexistent_post(): void
    {
        $this->patchJson('/api/social/posts/999999/approve', [], $this->headers())
            ->assertNotFound();
    }

    public function test_create_rejects_invalid_platform(): void
    {
        $this->postJson('/api/social/posts', ['tema' => 'x', 'platforms' => ['twitter']], $this->headers())
            ->assertUnprocessable();
    }

    public function test_can_reject_post_and_store_reason_in_payload(): void
    {
        $post = $this->criarPost();

        $this->patchJson("/api/social/posts/{$post->id}/reject", ['motivo' => 'Tema fora da marca'], $this->headers())
            ->assertOk()
            ->assertJsonPath('post.status', 'rejeitado');

        $this->assertSame('rejeitado', $post->fresh()->status);
        $this->assertSame(['rejection_reason' => 'Tema fora da marca'], $post->fresh()->payload);
    }
}
