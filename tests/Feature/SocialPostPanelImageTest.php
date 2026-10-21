<?php

namespace Tests\Feature;

use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SocialPost;
use App\Services\SocialImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SocialPostPanelImageTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private string $clinicId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public', ['url' => 'https://social.test/storage']);

        config([
            'services.social_ai.webhook_url' => 'https://n8n.test/webhook/LJSocialAI01',
        ]);

        $this->clinicId = $this->criarClinica('Clínica Painel Imagem', 'painel-imagem@test.com')['clinic']->id;
    }

    private function usuarioComPermissao(string ...$permissoes): User
    {
        $user = User::create([
            'clinic_id' => $this->clinicId,
            'name' => 'Operador Social',
            'email' => 'operador_'.uniqid().'@test.com',
            'password' => 'senha-teste-123',
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'papel_'.uniqid()]);

        foreach ($permissoes as $permissao) {
            [$module, $action] = array_pad(explode('.', $permissao, 2), 2, $permissao);

            $role->permissions()->attach(Permission::create([
                'name' => $permissao,
                'module' => $module,
                'action' => $action,
            ])->id);
        }

        $user->assignRole($role);

        return $user;
    }

    private function criarPost(array $atributos = []): SocialPost
    {
        return SocialPost::create(array_merge([
            'titulo' => 'Post de teste',
            'tema' => 'Odontologia',
            'status' => 'pendente',
            'platforms' => ['instagram'],
            'payload' => [],
        ], $atributos));
    }

    /**
     * Imagem real gerada com GD; os bytes extras mantêm o arquivo válido para o
     * teste de limite bater na regra de tamanho, e não na de tipo.
     */
    private function imagem(string $formato, int $bytes = 1024): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'social-painel');

        $imagem = imagecreatetruecolor(8, 8);

        match ($formato) {
            'jpeg' => imagejpeg($imagem, $path),
            'webp' => imagewebp($imagem, $path),
            default => imagepng($imagem, $path),
        };

        imagedestroy($imagem);

        if ($bytes > filesize($path)) {
            file_put_contents($path, str_repeat("\0", $bytes - filesize($path)), FILE_APPEND);
        }

        return new UploadedFile($path, 'imagem.'.$formato, 'image/'.$formato, null, true);
    }

    public function test_envia_imagem_png(): void
    {
        $user = $this->usuarioComPermissao('social.manage');
        $post = $this->criarPost();

        $this->actingAs($user)
            ->post(route('social.image', $post), ['image' => $this->imagem('png')])
            ->assertRedirect(route('social.show', $post))
            ->assertSessionHas('success');

        $fresh = $post->fresh();

        $this->assertMatchesRegularExpression(
            '#^https://social\.test/storage/social/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#',
            $fresh->image_url
        );
        $this->assertSame('manual', $fresh->payload['image_generation']['provider']);
        $this->assertSame('image/png', $fresh->payload['image_generation']['media_type']);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_envia_imagem_jpeg(): void
    {
        $user = $this->usuarioComPermissao('social.manage');
        $post = $this->criarPost();

        $this->actingAs($user)
            ->post(route('social.image', $post), ['image' => $this->imagem('jpeg')])
            ->assertRedirect(route('social.show', $post))
            ->assertSessionHas('success');

        $fresh = $post->fresh();

        $this->assertMatchesRegularExpression(
            '#^https://social\.test/storage/social/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#',
            $fresh->image_url
        );
        $this->assertSame('image/jpeg', $fresh->payload['image_generation']['media_type']);
    }

    public function test_envia_imagem_webp(): void
    {
        $user = $this->usuarioComPermissao('social.manage');
        $post = $this->criarPost();

        $this->actingAs($user)
            ->post(route('social.image', $post), ['image' => $this->imagem('webp')])
            ->assertRedirect(route('social.show', $post))
            ->assertSessionHas('success');

        $fresh = $post->fresh();

        $this->assertMatchesRegularExpression(
            '#^https://social\.test/storage/social/\d{4}/\d{2}/[0-9a-f-]{36}\.webp$#',
            $fresh->image_url
        );
        $this->assertSame('image/webp', $fresh->payload['image_generation']['media_type']);
    }

    public function test_rejeita_arquivo_que_nao_e_imagem(): void
    {
        $user = $this->usuarioComPermissao('social.manage');
        $post = $this->criarPost();

        $this->actingAs($user)
            ->post(route('social.image', $post), [
                'image' => UploadedFile::fake()->create('nota.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertNull($post->fresh()->image_url);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_rejeita_arquivo_maior_que_10mb(): void
    {
        $user = $this->usuarioComPermissao('social.manage');
        $post = $this->criarPost();

        $this->actingAs($user)
            ->post(route('social.image', $post), ['image' => $this->imagem('png', 11 * 1024 * 1024)])
            ->assertSessionHasErrors('image');

        $this->assertNull($post->fresh()->image_url);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_nao_autenticado_e_redirecionado_para_login(): void
    {
        $post = $this->criarPost();

        $this->post(route('social.image', $post), ['image' => $this->imagem('png')])
            ->assertRedirect(route('login'));

        $this->assertNull($post->fresh()->image_url);
    }

    public function test_exige_permissao_manage(): void
    {
        $user = $this->usuarioComPermissao('social.view');
        $post = $this->criarPost();

        $this->actingAs($user)
            ->post(route('social.image', $post), ['image' => $this->imagem('png')])
            ->assertForbidden();

        $this->assertNull($post->fresh()->image_url);
    }

    public function test_post_de_outra_clinica_retorna_404(): void
    {
        $user = $this->usuarioComPermissao('social.manage');

        $this->criarClinica('Outra Clínica', 'outra-painel@test.com');
        $postFromB = $this->criarPost(['titulo' => 'Post B']);

        $this->conectar(Clinic::findOrFail($this->clinicId));

        $this->actingAs($user)
            ->post(route('social.image', $postFromB), ['image' => $this->imagem('png')])
            ->assertNotFound();
    }

    public function test_substitui_imagem_preservando_demais_campos_e_arquivo_antigo(): void
    {
        Storage::disk('public')->put('social/2020/01/antiga.png', 'conteudo');
        $antigaUrl = Storage::disk('public')->url('social/2020/01/antiga.png');

        $user = $this->usuarioComPermissao('social.manage');

        $post = $this->criarPost([
            'titulo' => 'Título fixo',
            'legenda_instagram' => 'Legenda original',
            'hashtags' => ['#a', '#b'],
            'cta' => 'Agende agora',
            'status' => 'aprovado',
            'approved_at' => now(),
            'image_url' => $antigaUrl,
            'payload' => ['image_generation' => ['error' => 'falha anterior']],
        ]);

        $this->actingAs($user)
            ->post(route('social.image', $post), ['image' => $this->imagem('png')])
            ->assertRedirect(route('social.show', $post))
            ->assertSessionHas('success');

        $fresh = $post->fresh();

        $this->assertNotSame($antigaUrl, $fresh->image_url);
        $this->assertSame('Título fixo', $fresh->titulo);
        $this->assertSame('Legenda original', $fresh->legenda_instagram);
        $this->assertSame(['#a', '#b'], $fresh->hashtags);
        $this->assertSame('Agende agora', $fresh->cta);
        $this->assertSame('aprovado', $fresh->status);
        $this->assertSame('manual', $fresh->payload['image_generation']['provider']);
        $this->assertArrayNotHasKey('error', $fresh->payload['image_generation']);
        $this->assertTrue(Storage::disk('public')->exists('social/2020/01/antiga.png'));
        $this->assertCount(2, Storage::disk('public')->allFiles());
    }

    public function test_falha_de_armazenamento_mantem_post_sem_imagem(): void
    {
        $this->mock(SocialImageStorage::class, function ($mock) {
            $mock->shouldReceive('store')->once()->andReturnNull();
        });

        $user = $this->usuarioComPermissao('social.manage');
        $post = $this->criarPost();

        $this->actingAs($user)
            ->post(route('social.image', $post), ['image' => $this->imagem('png')])
            ->assertSessionHas('error');

        $this->assertNull($post->fresh()->image_url);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_criacao_com_arquivo_envia_url_armazenada_ao_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'post' => ['id' => 99]], 200),
        ]);

        $user = $this->usuarioComPermissao('social.manage');

        $this->actingAs($user)
            ->post(route('social.store'), [
                'tema' => 'Clareamento dental',
                'objetivo' => 'Atrair novos pacientes',
                'publico' => 'Adultos',
                'tipo' => 'promocional',
                'platforms' => ['instagram'],
                'image' => $this->imagem('png'),
            ])
            ->assertRedirect(route('social.show', 99));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_starts_with((string) ($body['image_url'] ?? ''), 'https://social.test/storage/social/')
                && ! array_key_exists('image', $body);
        });
    }
}
