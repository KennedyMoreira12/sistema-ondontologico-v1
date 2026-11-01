<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformSocialPostRequest;
use App\Models\Central\PlatformSocialPost;
use App\Models\Central\PlatformSocialSetting;
use App\Services\Platform\PlatformLogger;
use App\Services\Platform\Social\SocialPublisher;
use App\Services\SocialImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Posts próprios da plataforma (a LJ) para Instagram e Facebook.
 *
 * Fica no banco central e é exclusiva do super admin. A publicação é manual: o
 * botão "Publicar agora" envia o post às contas configuradas em
 * `platform_social_settings`. Nada aqui pertence a uma clínica.
 */
class SocialPostController extends Controller
{
    public function __construct(
        private SocialImageStorage $images,
        private SocialPublisher $publisher,
    ) {}

    public function index(Request $request): View
    {
        $posts = PlatformSocialPost::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('platform.social.index', [
            'posts' => $posts,
            'setting' => PlatformSocialSetting::current(),
        ]);
    }

    public function create(): View
    {
        return view('platform.social.create', [
            'setting' => PlatformSocialSetting::current(),
        ]);
    }

    public function store(StorePlatformSocialPostRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        $post = new PlatformSocialPost();
        $post->admin_user_id = $request->user()?->id;
        $post->titulo = $dados['titulo'];
        $post->legenda_instagram = $dados['legenda_instagram'] ?? null;
        $post->legenda_facebook = $dados['legenda_facebook'] ?? null;
        $post->hashtags = $this->hashtags($dados['hashtags'] ?? null);
        $post->platforms = array_values($dados['platforms']);
        $post->status = PlatformSocialPost::STATUS_RASCUNHO;

        if ($request->hasFile('image')) {
            $salvo = $this->images->store($request->file('image'));

            if ($salvo === null) {
                return back()->withInput()->with('error', 'Não foi possível salvar a imagem. Verifique o disco público.');
            }

            if (! $this->images->urlIsPublicHttps($salvo['url'])) {
                $this->images->delete($salvo['path']);

                return back()->withInput()->with('error', 'A URL pública da imagem não é HTTPS. Confira o APP_URL.');
            }

            $post->image_url = $salvo['url'];
            $post->image_path = $salvo['path'];
        }

        $post->save();

        PlatformLogger::log('social.post_criado', 'platform_social_post', $post->id, ['titulo' => $post->titulo]);

        return redirect()
            ->route('painel.social.show', $post)
            ->with('success', 'Post criado. Revise e clique em Publicar agora.');
    }

    public function show(PlatformSocialPost $post): View
    {
        return view('platform.social.show', [
            'post' => $post,
            'setting' => PlatformSocialSetting::current(),
        ]);
    }

    /**
     * Publica agora, nas redes escolhidas, pelas contas da LJ. Síncrono: a
     * resposta já traz o estado por rede. A decisão de cada rede é preservada —
     * uma pode falhar sem derrubar a outra.
     */
    public function publish(PlatformSocialPost $post): RedirectResponse
    {
        $setting = PlatformSocialSetting::current();

        if ($setting === null) {
            return back()->with('error', 'Configure as contas do Instagram e Facebook antes de publicar.');
        }

        $resultados = $this->publisher->publicar($post, $setting);

        $this->aplicarResultados($post, $resultados);

        PlatformLogger::log('social.post_publicado', 'platform_social_post', $post->id, [
            'facebook' => $resultados['facebook']['status'],
            'instagram' => $resultados['instagram']['status'],
        ]);

        if ($post->fresh()->status === PlatformSocialPost::STATUS_PUBLICADO) {
            return back()->with('success', 'Post publicado com sucesso.');
        }

        return back()->with('error', $this->mensagemDeErro($resultados));
    }

    public function destroy(PlatformSocialPost $post): RedirectResponse
    {
        if (filled($post->image_path)) {
            $this->images->delete($post->image_path);
        }

        $titulo = $post->titulo;
        $post->delete();

        PlatformLogger::log('social.post_excluido', 'platform_social_post', $post->id, ['titulo' => $titulo]);

        return redirect()
            ->route('painel.social.index')
            ->with('success', "Post \"{$titulo}\" excluído.");
    }

    /**
     * Aplica o resultado por rede ao post e recalcula o status geral.
     *
     * @param  array<string, array{status: string, id: ?string, erro: ?string, categoria: string}>  $resultados
     */
    protected function aplicarResultados(PlatformSocialPost $post, array $resultados): void
    {
        $atualizacao = [
            'facebook_status' => $resultados['facebook']['status'],
            'facebook_post_id' => $resultados['facebook']['id'] ?: $post->facebook_post_id,
            'instagram_status' => $resultados['instagram']['status'],
            'instagram_media_id' => $resultados['instagram']['id'] ?: $post->instagram_media_id,
        ];

        $tentadas = collect($resultados)
            ->reject(fn (array $resultado): bool => $resultado['status'] === PlatformSocialPost::REDE_IGNORADO);

        if ($tentadas->isEmpty()) {
            $atualizacao['error'] = 'Escolha ao menos uma rede (Facebook ou Instagram) para publicar.';

            $post->update($atualizacao);

            return;
        }

        if ($tentadas->every(fn (array $resultado): bool => $resultado['status'] === PlatformSocialPost::REDE_PUBLICADO)) {
            $atualizacao['status'] = PlatformSocialPost::STATUS_PUBLICADO;
            $atualizacao['error'] = null;
            $atualizacao['published_at'] = now();
        } else {
            $atualizacao['status'] = $tentadas->contains(fn (array $resultado): bool => $resultado['status'] === PlatformSocialPost::REDE_PUBLICADO)
                ? PlatformSocialPost::STATUS_PARCIAL
                : PlatformSocialPost::STATUS_ERRO;
            $atualizacao['error'] = $this->mensagemDeErro($resultados);
        }

        $post->update($atualizacao);
    }

    /**
     * @param  array<string, array{status: string, id: ?string, erro: ?string, categoria: string}>  $resultados
     */
    protected function mensagemDeErro(array $resultados): string
    {
        $mensagens = collect($resultados)
            ->filter(fn (array $resultado): bool => $resultado['status'] === PlatformSocialPost::REDE_ERRO)
            ->map(fn (array $resultado, string $rede): string => $this->rotulo($rede).': '.$resultado['erro'])
            ->implode(' ');

        return $mensagens !== '' ? $mensagens : 'Não foi possível publicar o post.';
    }

    /**
     * Normaliza as hashtags para o formato guardado (`#tag`), sem repetir.
     *
     * @return array<int, string>
     */
    protected function hashtags(?string $texto): array
    {
        if (! filled($texto)) {
            return [];
        }

        return collect(preg_split('/[\s,]+/', (string) $texto) ?: [])
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->map(fn (string $tag): string => str_starts_with($tag, '#') ? $tag : '#'.$tag)
            ->unique()
            ->values()
            ->all();
    }

    protected function rotulo(string $rede): string
    {
        return $rede === 'instagram' ? 'Instagram' : 'Facebook';
    }
}
