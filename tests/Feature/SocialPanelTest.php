<?php

namespace Tests\Feature;

use App\Models\SocialPost;
use App\Models\SocialSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class SocialPanelTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

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
            'titulo' => 'Clareamento dental',
            'legenda_instagram' => 'Volte a sorrir com segurança.',
            'legenda_facebook' => 'Agende sua avaliação.',
            'hashtags' => '#odonto #sorriso',
            'platforms' => ['instagram'],
            'image_url' => 'https://exemplo.test/imagem.jpg',
        ], $atributos);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function criarSetting(array $atributos = []): SocialSetting
    {
        return SocialSetting::create(array_merge([
            'facebook_page_id' => '1234567890',
            'instagram_business_id' => '17841400000000000',
            'page_access_token' => 'token-de-teste',
            'graph_version' => 'v23.0',
        ], $atributos));
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
            ->assertSee('Criar post');
    }

    public function test_cria_post_manual_como_rascunho_pendente(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $this->actingAs($user)
            ->post(route('social.store'), $this->dadosFormulario([
                'titulo' => 'Clareamento dental',
                'hashtags' => 'odonto, sorriso',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $post = SocialPost::firstOrFail();

        $this->assertSame('pendente', $post->status);
        $this->assertSame('Clareamento dental', $post->titulo);
        $this->assertSame('Clareamento dental', $post->tema);
        $this->assertSame(['#odonto', '#sorriso'], $post->hashtags);
        $this->assertSame(['instagram'], $post->platforms);
        $this->assertSame('https://exemplo.test/imagem.jpg', $post->image_url);

        $this->assertTenantDatabaseHas('social_posts', [
            'id' => $post->id,
            'status' => 'pendente',
            'titulo' => 'Clareamento dental',
        ]);
    }

    public function test_formulario_valida_plataformas_e_imagem_https(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $this->actingAs($user)
            ->post(route('social.store'), $this->dadosFormulario([
                'platforms' => [],
                'image_url' => 'http://insegura.test/imagem.jpg',
            ]))
            ->assertSessionHasErrors(['platforms', 'image_url']);

        $this->assertTenantDatabaseCount('social_posts', 0);
    }

    public function test_configuracao_de_social_exige_permissao_manage(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.view');

        $this->actingAs($user)
            ->get(route('social.configuracao'))
            ->assertForbidden();
    }

    public function test_salva_configuracao_e_guarda_token_cifrado(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $this->actingAs($user)
            ->put(route('social.configuracao.update'), [
                'facebook_page_id' => '1234567890',
                'instagram_business_id' => '17841400000000000',
                'page_access_token' => 'TOKEN-SUPER-SECRETO',
                'graph_version' => 'v23.0',
            ])
            ->assertRedirect(route('social.configuracao'))
            ->assertSessionHas('success');

        $setting = SocialSetting::firstOrFail();

        $this->assertSame('1234567890', $setting->facebook_page_id);
        $this->assertSame('TOKEN-SUPER-SECRETO', $setting->page_access_token);

        // O token jamais é gravado em claro no banco.
        $this->assertTenantDatabaseMissing('social_settings', ['page_access_token' => 'TOKEN-SUPER-SECRETO']);
    }

    public function test_salvar_configuracao_em_branco_mantem_token(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $this->criarSetting(['page_access_token' => 'token-antigo']);

        $this->actingAs($user)
            ->put(route('social.configuracao.update'), [
                'facebook_page_id' => '999',
                'page_access_token' => '',
            ])
            ->assertSessionHas('success');

        $setting = SocialSetting::firstOrFail();

        $this->assertSame('token-antigo', $setting->page_access_token);
        $this->assertSame('999', $setting->facebook_page_id);
    }

    public function test_publicacao_real_exige_permissao_manage(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.view');

        $post = $this->criarPost();

        $this->actingAs($user)
            ->post(route('social.publicar', $post))
            ->assertForbidden();
    }

    public function test_publicar_sem_configuracao_retorna_erro(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.manage');

        $post = $this->criarPost();

        $this->actingAs($user)
            ->post(route('social.publicar', $post))
            ->assertSessionHas('error');

        $this->assertNull($post->refresh()->published_at);
    }

    public function test_publica_no_facebook_com_sucesso(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => 'fb-123'], 200),
        ]);

        [, $user] = $this->criarUsuarioComPermissao('social.manage');
        $this->criarSetting();

        $post = $this->criarPost([
            'platforms' => ['facebook'],
            'legenda_facebook' => 'Olá, Facebook',
        ]);

        $this->actingAs($user)
            ->post(route('social.publicar', $post))
            ->assertSessionHas('success');

        $fresh = $post->refresh();

        $this->assertSame('publicado', $fresh->facebook_status);
        $this->assertSame('fb-123', $fresh->facebook_post_id);
        $this->assertNotNull($fresh->published_at);
        $this->assertNull($fresh->error);
    }

    public function test_publica_no_instagram_com_sucesso_em_dois_passos(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => 'ig-media-id'], 200),
        ]);

        [, $user] = $this->criarUsuarioComPermissao('social.manage');
        $this->criarSetting();

        $post = $this->criarPost([
            'platforms' => ['instagram'],
            'image_url' => 'https://exemplo.test/imagem.jpg',
        ]);

        $this->actingAs($user)
            ->post(route('social.publicar', $post))
            ->assertSessionHas('success');

        $fresh = $post->refresh();

        $this->assertSame('publicado', $fresh->instagram_status);
        $this->assertSame('ig-media-id', $fresh->instagram_media_id);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_instagram_sem_imagem_publica_com_erro(): void
    {
        Http::fake();

        [, $user] = $this->criarUsuarioComPermissao('social.manage');
        $this->criarSetting();

        $post = $this->criarPost(['platforms' => ['instagram']]);

        $this->actingAs($user)
            ->post(route('social.publicar', $post))
            ->assertSessionHas('error');

        $fresh = $post->refresh();

        $this->assertSame('erro', $fresh->instagram_status);
        $this->assertNull($fresh->published_at);
        $this->assertNotNull($fresh->error);
    }

    public function test_falha_da_meta_nao_registra_publicacao(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid parameter', 'code' => 100],
            ], 400),
        ]);

        [, $user] = $this->criarUsuarioComPermissao('social.manage');
        $this->criarSetting();

        $post = $this->criarPost(['platforms' => ['facebook']]);

        $this->actingAs($user)
            ->post(route('social.publicar', $post))
            ->assertSessionHas('error');

        $fresh = $post->refresh();

        $this->assertSame('erro', $fresh->facebook_status);
        $this->assertNull($fresh->facebook_post_id);
        $this->assertNull($fresh->published_at);
    }

    public function test_publicar_nao_altera_status_de_aprovacao(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => 'fb-9'], 200),
        ]);

        [, $user] = $this->criarUsuarioComPermissao('social.manage');
        $this->criarSetting();

        $post = $this->criarPost(['platforms' => ['facebook'], 'status' => 'aprovado']);

        $this->actingAs($user)
            ->post(route('social.publicar', $post))
            ->assertSessionHas('success');

        $this->assertSame('aprovado', $post->refresh()->status);
    }
}
