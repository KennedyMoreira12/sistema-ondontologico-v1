<?php

namespace Tests\Feature;

use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class SocialPanelTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.social_ai.webhook_url' => 'https://n8n.test/webhook/LJSocialAI01/webhook/lj-social-ai',
        ]);
    }

    private function criarPost(array $atributos = []): SocialPost
    {
        return SocialPost::create(array_merge([
            'titulo' => 'Post de teste',
            'tema' => 'Odontologia',
            'status' => 'pendente',
            'platforms' => ['instagram', 'facebook'],
            'payload' => [],
        ], $atributos));
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosFormulario(array $atributos = []): array
    {
        return array_merge([
            'tema' => 'Clareamento dental',
            'objetivo' => 'Atrair novos pacientes',
            'publico' => 'Adultos de 25 a 45 anos',
            'tipo' => 'promocional',
            'observacoes' => 'Destacar a segurança do procedimento',
            'platforms' => ['instagram'],
            'image_url' => 'https://exemplo.test/imagem.jpg',
            'scheduled_at' => '2026-11-01T09:00',
        ], $atributos);
    }

    public function test_usuario_sem_role_nao_acessa_social(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->get(route('social.index'))
            ->assertForbidden();
    }

    public function test_aprovacao_exige_permissao_manage(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.view');

        $post = $this->criarPost();

        $this->actingAs($user)
            ->patch(route('social.approve', $post))
            ->assertForbidden();
    }

    public function test_index_lista_publicacoes_com_filtro_de_status(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.view');

        $this->criarPost(['titulo' => 'Post Pendente']);
        $this->criarPost(['titulo' => 'Post Aprovado', 'status' => 'aprovado']);

        $this->actingAs($user)
            ->get(route('social.index'))
            ->assertOk()
            ->assertSee('Post Pendente')
            ->assertSee('Post Aprovado');

        $this->actingAs($user)
            ->get(route('social.index', ['status' => 'aprovado']))
            ->assertOk()
            ->assertSee('Post Aprovado')
            ->assertDontSee('Post Pendente');
    }

    public function test_show_mostra_conteudo_imagem_e_motivo_de_rejeicao(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.view');

        $post = $this->criarPost([
            'titulo' => 'Sorriso Saudável',
            'legenda_instagram' => 'Legenda de exemplo',
            'status' => 'rejeitado',
            'image_url' => 'https://exemplo.test/imagem.jpg',
            'payload' => ['rejection_reason' => 'Conteúdo duplicado'],
        ]);

        $this->actingAs($user)
            ->get(route('social.show', $post))
            ->assertOk()
            ->assertSee('Sorriso Saudável')
            ->assertSee('Legenda de exemplo')
            ->assertSee('https://exemplo.test/imagem.jpg')
            ->assertSee('Conteúdo duplicado');
    }

    public function test_aprova_publicacao_pendente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $post = $this->criarPost();

        $this->actingAs($user)
            ->patch(route('social.approve', $post))
            ->assertRedirect(route('social.show', $post))
            ->assertSessionHas('success');

        $this->assertTenantDatabaseHas('social_posts', [
            'id' => $post->id,
            'status' => 'aprovado',
        ]);

        $this->assertNotNull($post->refresh()->approved_at);
    }

    public function test_nao_modifica_publicacao_ja_aprovada(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $post = $this->criarPost(['status' => 'aprovado']);

        $this->actingAs($user)
            ->patch(route('social.approve', $post))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTenantDatabaseHas('social_posts', [
            'id' => $post->id,
            'status' => 'aprovado',
        ]);
    }

    public function test_rejeita_publicacao_com_motivo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $post = $this->criarPost();

        $this->actingAs($user)
            ->patch(route('social.reject', $post), [
                'motivo' => 'Cliente pediu revisão do texto',
            ])
            ->assertRedirect(route('social.show', $post))
            ->assertSessionHas('success');

        $this->assertTenantDatabaseHas('social_posts', [
            'id' => $post->id,
            'status' => 'rejeitado',
        ]);

        $this->assertSame('Cliente pediu revisão do texto', $post->refresh()->payload['rejection_reason']);
    }

    public function test_rejeitacao_exige_motivo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $post = $this->criarPost();

        $this->actingAs($user)
            ->patch(route('social.reject', $post))
            ->assertSessionHasErrors('motivo');

        $this->assertTenantDatabaseHas('social_posts', [
            'id' => $post->id,
            'status' => 'pendente',
        ]);
    }

    public function test_novo_post_exige_permissao_manage(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.view');

        $this->actingAs($user)
            ->get(route('social.create'))
            ->assertForbidden();
    }

    public function test_botao_novo_post_nao_aparece_sem_permissao_manage(): void
    {
        [, $view] = $this->criarUsuarioComPermissao('social.view');

        $this->actingAs($view)
            ->get(route('social.index'))
            ->assertOk()
            ->assertDontSee('Novo Post');
    }

    public function test_botao_novo_post_aparece_com_permissao_manage(): void
    {
        [, $manage] = $this->criarUsuarioComPermissao('social.view', 'social.manage');

        $this->actingAs($manage)
            ->get(route('social.index'))
            ->assertOk()
            ->assertSee('Novo Post');
    }

    public function test_formulario_novo_post_renderiza(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $this->actingAs($user)
            ->get(route('social.create'))
            ->assertOk()
            ->assertSee('Gerar conteúdo');
    }

    public function test_gera_conteudo_pelo_webhook_e_redireciona(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'post' => ['id' => 321]], 200),
        ]);

        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $this->actingAs($user)
            ->post(route('social.store'), $this->dadosFormulario())
            ->assertRedirect(route('social.show', 321))
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === config('services.social_ai.webhook_url')
                && $body['tema'] === 'Clareamento dental'
                && $body['platforms'] === ['instagram']
                && $body['image_url'] === 'https://exemplo.test/imagem.jpg'
                && ! array_key_exists('model', $body)
                && ! array_key_exists('token', $body);
        });
    }

    public function test_webhook_recusando_entrada_nao_cria_post(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'error' => 'tema obrigatório'], 200),
        ]);

        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $this->actingAs($user)
            ->post(route('social.store'), $this->dadosFormulario())
            ->assertSessionHas('error');

        $this->assertTenantDatabaseCount('social_posts', 0);
    }

    public function test_webhook_indisponivel_nao_cria_post(): void
    {
        Http::fake([
            '*' => Http::response('erro interno', 500),
        ]);

        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $this->actingAs($user)
            ->post(route('social.store'), $this->dadosFormulario())
            ->assertSessionHas('error');

        $this->assertTenantDatabaseCount('social_posts', 0);
    }

    public function test_formulario_valida_plataformas_e_imagem_https(): void
    {
        Http::fake();

        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $this->actingAs($user)
            ->post(route('social.store'), $this->dadosFormulario([
                'platforms' => [],
                'image_url' => 'http://insegura.test/imagem.jpg',
            ]))
            ->assertSessionHasErrors(['platforms', 'image_url']);

        Http::assertNothingSent();
    }
}
