<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSocialPostDraftRequest;
use App\Http\Requests\UploadSocialPostImageRequest;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use App\Services\AuditLogger;
use App\Services\Social\SocialPublisher;
use App\Services\SocialImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialPostController extends Controller
{
    public function __construct(
        private SocialImageStorage $images,
        private SocialPublisher $publisher,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('social.view');

        $posts = SocialPost::query()
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $termo = $request->string('q')->trim();

                if ($termo !== '') {
                    $query->where(function ($q) use ($termo) {
                        $q->where('titulo', 'like', "%{$termo}%")
                            ->orWhere('tema', 'like', "%{$termo}%");
                    });
                }
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('social.index', [
            'posts' => $posts,
            'setting' => SocialSetting::current(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('social.manage');

        return view('social.create');
    }

    /**
     * Cria o post direto pelo formulário de composição manual.
     *
     * O texto (título, legendas e hashtags) chega pronto do operador e o post
     * nasce como rascunho "pendente", aguardando aprovação — nada é publicado
     * automaticamente. `tema` recebe o título só para satisfazer a coluna
     * obrigatória herdada do fluxo antigo e manter a busca funcionando.
     */
    public function store(StoreSocialPostDraftRequest $request): RedirectResponse
    {
        $this->authorize('social.manage');

        $dados = $request->validated();
        $imageUrl = $dados['image_url'] ?? null;

        if ($request->hasFile('image')) {
            $salvo = $this->images->store($request->file('image'));

            if ($salvo === null) {
                return redirect()->back()->withInput()
                    ->with('error', 'Não foi possível armazenar a imagem. Tente novamente.');
            }

            if (! $this->images->urlIsPublicHttps($salvo['url'])) {
                $this->images->delete($salvo['path']);

                return redirect()->back()->withInput()
                    ->with('error', 'A URL pública da imagem não é HTTPS. Confira o APP_URL.');
            }

            $imageUrl = $salvo['url'];
        }

        $post = SocialPost::create([
            'user_id' => $request->user()?->id,
            'tema' => $dados['titulo'],
            'titulo' => $dados['titulo'],
            'legenda_instagram' => $dados['legenda_instagram'] ?? null,
            'legenda_facebook' => $dados['legenda_facebook'] ?? null,
            'hashtags' => $this->normalizarHashtags($dados['hashtags'] ?? null),
            'image_url' => $imageUrl,
            'platforms' => array_values($dados['platforms']),
            'status' => 'pendente',
            'facebook_status' => 'pendente',
            'instagram_status' => 'pendente',
        ]);

        AuditLogger::log('criar', 'social_posts', $post->id, depois: ['origem' => 'formulario_web']);

        return redirect()->route('social.show', $post)
            ->with('success', 'Post criado e aguardando aprovação.');
    }

    public function show(SocialPost $socialPost): View
    {
        $this->authorize('social.view');

        return view('social.show', [
            'socialPost' => $socialPost,
            'setting' => SocialSetting::current(),
        ]);
    }

    /**
     * Grava a imagem enviada à mão pelo operador e atualiza só a URL do post.
     *
     * Não toca em legenda, hashtags, CTA, status nem datas, e não apaga o
     * arquivo anterior: se a imagem nova não servir, o link antigo continua
     * registrado no payload/histórico. O post é resolvido pelo bind da conexão
     * da clínica atual, então um id de outra clínica nem chega aqui (404).
     */
    public function uploadImage(UploadSocialPostImageRequest $request, SocialPost $socialPost): RedirectResponse
    {
        $this->authorize('social.manage');

        $anterior = $socialPost->image_url;
        $salvo = $this->images->store($request->file('image'));

        if ($salvo === null) {
            return redirect()->back()->with('error', 'Não foi possível armazenar a imagem. Tente novamente.');
        }

        if (! $this->images->urlIsPublicHttps($salvo['url'])) {
            $this->images->delete($salvo['path']);

            return redirect()->back()->with('error', 'A URL pública da imagem não é HTTPS. Confira o APP_URL.');
        }

        $payload = $socialPost->payload ?? [];
        $payload['image_generation'] = array_merge($payload['image_generation'] ?? [], [
            'provider' => 'manual',
            'media_type' => $salvo['mime'],
        ]);
        unset($payload['image_generation']['error']);

        $socialPost->update([
            'image_url' => $salvo['url'],
            'payload' => $payload,
        ]);

        AuditLogger::log('enviar_imagem', 'social_posts', $socialPost->id, antes: ['image_url' => $anterior], depois: ['image_url' => $salvo['url']]);

        return redirect()->route('social.show', $socialPost)->with('success', 'Imagem atualizada com sucesso.');
    }

    public function approve(SocialPost $socialPost): RedirectResponse
    {
        $this->authorize('social.manage');

        if ($socialPost->status !== 'pendente') {
            return redirect()->back()->with('error', 'Somente publicações pendentes podem ser aprovadas.');
        }

        $socialPost->update([
            'status' => 'aprovado',
            'approved_at' => now(),
        ]);

        AuditLogger::log('aprovar', 'social_posts', $socialPost->id, depois: ['status' => 'aprovado']);

        return redirect()->route('social.show', $socialPost)->with('success', 'Publicação aprovada com sucesso.');
    }

    public function reject(Request $request, SocialPost $socialPost): RedirectResponse
    {
        $this->authorize('social.manage');

        $validated = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        if ($socialPost->status !== 'pendente') {
            return redirect()->back()->with('error', 'Somente publicações pendentes podem ser rejeitadas.');
        }

        $socialPost->update([
            'status' => 'rejeitado',
            'approved_at' => null,
            'published_at' => null,
            'payload' => array_merge($socialPost->payload ?? [], ['rejection_reason' => $validated['motivo']]),
        ]);

        AuditLogger::log('rejeitar', 'social_posts', $socialPost->id, depois: ['status' => 'rejeitado', 'motivo' => $validated['motivo']]);

        return redirect()->route('social.show', $socialPost)->with('success', 'Publicação rejeitada com sucesso.');
    }

    /**
     * Publica agora, nas redes escolhidas, pelas contas da clínica. Síncrono: a
     * resposta já traz o estado por rede. A decisão de cada rede é preservada —
     * uma pode falhar sem derrubar a outra.
     *
     * Não toca em `status`: a aprovação do fluxo (pendente/aprovado/rejeitado) é
     * outra decisão, e publicar não a sobrescreve.
     */
    public function publish(SocialPost $socialPost): RedirectResponse
    {
        $this->authorize('social.manage');

        $setting = SocialSetting::current();

        if ($setting === null || ! $setting->page_access_token) {
            return redirect()->back()->with('error', 'Configure as contas do Instagram e Facebook da clínica antes de publicar.');
        }

        $resultados = $this->publisher->publicar($socialPost, $setting);

        $this->aplicarResultados($socialPost, $resultados);

        AuditLogger::log('publicar', 'social_posts', $socialPost->id, depois: [
            'facebook' => $resultados['facebook']['status'],
            'instagram' => $resultados['instagram']['status'],
        ]);

        if ($this->publicouTudo($resultados)) {
            return redirect()->route('social.show', $socialPost)->with('success', 'Post publicado com sucesso.');
        }

        return redirect()->route('social.show', $socialPost)->with('error', $this->mensagemDeErro($resultados));
    }

    /**
     * Aplica o resultado por rede ao post SEM mexer no `status` de aprovação.
     *
     * @param  array<string, array{status: string, id: ?string, erro: ?string, categoria: string}>  $resultados
     */
    protected function aplicarResultados(SocialPost $post, array $resultados): void
    {
        $atualizacao = [
            'facebook_status' => $resultados['facebook']['status'],
            'facebook_post_id' => $resultados['facebook']['id'] ?: $post->facebook_post_id,
            'instagram_status' => $resultados['instagram']['status'],
            'instagram_media_id' => $resultados['instagram']['id'] ?: $post->instagram_media_id,
        ];

        $tentadas = collect($resultados)
            ->reject(fn (array $resultado): bool => $resultado['status'] === SocialPost::REDE_IGNORADO);

        if ($tentadas->isEmpty()) {
            $atualizacao['error'] = 'Escolha ao menos uma rede (Facebook ou Instagram) para publicar.';

            $post->update($atualizacao);

            return;
        }

        if ($tentadas->every(fn (array $resultado): bool => $resultado['status'] === SocialPost::REDE_PUBLICADO)) {
            $atualizacao['error'] = null;
            $atualizacao['published_at'] = now();
        } else {
            $atualizacao['error'] = $this->mensagemDeErro($resultados);
        }

        $post->update($atualizacao);
    }

    /**
     * @param  array<string, array{status: string, id: ?string, erro: ?string, categoria: string}>  $resultados
     */
    protected function publicouTudo(array $resultados): bool
    {
        $tentadas = collect($resultados)
            ->reject(fn (array $resultado): bool => $resultado['status'] === SocialPost::REDE_IGNORADO);

        return $tentadas->isNotEmpty()
            && $tentadas->every(fn (array $resultado): bool => $resultado['status'] === SocialPost::REDE_PUBLICADO);
    }

    /**
     * @param  array<string, array{status: string, id: ?string, erro: ?string, categoria: string}>  $resultados
     */
    protected function mensagemDeErro(array $resultados): string
    {
        $mensagens = collect($resultados)
            ->filter(fn (array $resultado): bool => $resultado['status'] === SocialPost::REDE_ERRO)
            ->map(fn (array $resultado, string $rede): string => $this->rotulo($rede).': '.$resultado['erro'])
            ->implode(' ');

        return $mensagens !== '' ? $mensagens : 'Não foi possível publicar o post.';
    }

    protected function rotulo(string $rede): string
    {
        return $rede === 'instagram' ? 'Instagram' : 'Facebook';
    }

    /**
     * Converte a linha de hashtags digitada em lista, garantindo o "#".
     *
     * @return array<int, string>
     */
    private function normalizarHashtags(?string $texto): array
    {
        if ($texto === null || trim($texto) === '') {
            return [];
        }

        $partes = preg_split('/[\s,]+/', trim($texto)) ?: [];

        $hashtags = [];

        foreach ($partes as $parte) {
            $parte = trim($parte);

            if ($parte === '') {
                continue;
            }

            $hashtags[] = str_starts_with($parte, '#') ? $parte : '#'.$parte;
        }

        return array_values(array_unique($hashtags));
    }
}
