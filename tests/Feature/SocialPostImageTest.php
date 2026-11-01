<?php

namespace Tests\Feature;

use App\Models\Central\IntegrationToken;
use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SocialPostImageTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private string $token;

    private string $clinicId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public', ['url' => 'https://social.test/storage']);

        $ctx = $this->criarClinica('Clínica Imagem');

        $this->token = $ctx['token'];
        $this->clinicId = $ctx['clinic']->id;
    }

    /**
     * O `Accept` espelha o header que o n8n manda: sem ele o Laravel devolve
     * 302 com redirect em vez de 422 com JSON, e o workflow não leria o erro.
     *
     * @return array<string, string>
     */
    private function headers(?string $token = null): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.($token ?? $this->token),
        ];
    }

    private function criarPost(array $data = []): SocialPost
    {
        return SocialPost::create(array_merge([
            'tema' => 'Higiene bucal',
            'objetivo' => 'Educar',
            'platforms' => ['facebook', 'instagram'],
        ], $data));
    }

    /**
     * PNG real gerado com GD e, quando pedido, com bytes a mais no fim.
     *
     * O tamanho grande precisa continuar sendo PNG válido: só assim o teste
     * de limite de 10 MB atinge a regra de tamanho em vez de morrer antes,
     * no tipo do arquivo.
     */
    private function png(int $bytes = 1024): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'social-img');

        $imagem = imagecreatetruecolor(8, 8);
        imagepng($imagem, $path);
        imagedestroy($imagem);

        if ($bytes > filesize($path)) {
            file_put_contents($path, str_repeat("\0", $bytes - filesize($path)), FILE_APPEND);
        }

        return new UploadedFile($path, 'imagem.png', 'image/png', null, true);
    }

    public function test_requer_token_de_integracao(): void
    {
        $post = $this->criarPost();

        $this->post(route('api.social.posts.image', $post->id), ['error' => 'x'], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    public function test_requer_ability_de_posts_sociais(): void
    {
        $restrito = IntegrationToken::generateToken();

        IntegrationToken::create([
            'clinic_id' => $this->clinicId,
            'name' => 'somente-leitura',
            'token_hash' => IntegrationToken::hashToken($restrito),
            'abilities' => ['patients.view'],
        ]);

        $post = $this->criarPost();

        $this->post(route('api.social.posts.image', $post->id), ['error' => 'x'], $this->headers($restrito))
            ->assertForbidden()
            ->assertJsonPath('message', 'Este token não tem permissão para [social_posts.manage].');
    }

    public function test_rejeita_requisicao_sem_imagem_e_sem_erro(): void
    {
        $post = $this->criarPost();

        $this->post(route('api.social.posts.image', $post->id), [], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertNull($post->fresh()->image_url);
    }

    public function test_rejeita_arquivo_que_nao_e_imagem(): void
    {
        $post = $this->criarPost();

        $this->post(route('api.social.posts.image', $post->id), [
            'image' => UploadedFile::fake()->create('nota.txt', 10, 'text/plain'),
        ], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_rejeita_arquivo_maior_que_10mb(): void
    {
        $post = $this->criarPost();

        $this->post(route('api.social.posts.image', $post->id), [
            'image' => $this->png(11 * 1024 * 1024),
        ], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertNull($post->fresh()->image_url);
    }

    public function test_grava_imagem_no_disco_publico_e_registra_o_custo(): void
    {
        $post = $this->criarPost();

        $this->post(route('api.social.posts.image', $post->id), [
            'image' => $this->png(),
            'model' => 'bytedance-seed/seedream-4.5',
            'cost_usd' => '0.04',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('post.id', $post->id);

        $fresh = $post->fresh();

        $this->assertMatchesRegularExpression(
            '#^https://social\.test/storage/social/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#',
            $fresh->image_url
        );
        $this->assertSame('pendente', $fresh->status);
        $this->assertSame('openrouter', $fresh->payload['image_generation']['provider']);
        $this->assertSame('bytedance-seed/seedream-4.5', $fresh->payload['image_generation']['model']);
        $this->assertSame('image/png', $fresh->payload['image_generation']['media_type']);
        $this->assertSame(0.04, $fresh->payload['image_generation']['cost_usd']);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_nao_substitui_imagem_ja_existente(): void
    {
        $post = $this->criarPost(['image_url' => 'https://exemplo.com/antiga.png']);

        $this->post(route('api.social.posts.image', $post->id), ['image' => $this->png()], $this->headers())
            ->assertUnprocessable();

        $this->assertSame('https://exemplo.com/antiga.png', $post->fresh()->image_url);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_registra_falha_de_geracao_sem_alterar_o_post(): void
    {
        $post = $this->criarPost();

        $this->post(route('api.social.posts.image', $post->id), [
            'error' => 'OpenRouter devolveu erro de cota.',
            'model' => 'bytedance-seed/seedream-4.5',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('image_error', 'OpenRouter devolveu erro de cota.');

        $fresh = $post->fresh();

        $this->assertNull($fresh->image_url);
        $this->assertSame('pendente', $fresh->status);
        $this->assertSame('OpenRouter devolveu erro de cota.', $fresh->payload['image_generation']['error']);
        $this->assertSame('bytedance-seed/seedream-4.5', $fresh->payload['image_generation']['model']);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_rejeita_url_publica_que_nao_e_https_e_apaga_o_arquivo(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $post = $this->criarPost();

        $this->post(route('api.social.posts.image', $post->id), ['image' => $this->png()], $this->headers())
            ->assertUnprocessable();

        $this->assertNull($post->fresh()->image_url);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_post_de_outra_clinica_retorna_404(): void
    {
        $this->criarClinica('Outra Clínica', 'outra-imagem@test.com');

        $postFromB = SocialPost::create([
            'tema' => 'Post B',
            'platforms' => ['facebook'],
        ]);

        $this->post(route('api.social.posts.image', $postFromB->id), ['error' => 'x'], $this->headers())
            ->assertNotFound();
    }
}
