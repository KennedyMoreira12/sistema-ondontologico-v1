<?php

namespace App\Services\Social;

use App\Models\SocialPost;
use App\Models\SocialSetting;
use App\Rules\PublicHttpsUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Publica os posts sociais DA CLÍNICA no Facebook e no Instagram dela.
 *
 * Fala direto com a Graph API da Meta usando as credenciais salvas em
 * `social_settings` (banco da clínica). É o equivalente de tenant do
 * App\Services\Platform\Social\SocialPublisher: a lógica é a mesma, mas as
 * contas são as da clínica, não as da LJ.
 *
 * O token nunca é logado nem devolvido. A mensagem crua da Meta é reduzida a
 * uma categoria legível; o detalhe técnico (código/subcódigo) vai para o log.
 */
class SocialPublisher
{
    public const CATEGORIA_OK = 'ok';
    public const CATEGORIA_SEM_CONFIGURACAO = 'sem_configuracao';
    public const CATEGORIA_AUTENTICACAO = 'autenticacao';
    public const CATEGORIA_PERMISSAO = 'permissao';
    public const CATEGORIA_CONTEUDO = 'conteudo';
    public const CATEGORIA_LIMITE = 'limite';
    public const CATEGORIA_META = 'meta';
    public const CATEGORIA_CONEXAO = 'conexao';
    public const CATEGORIA_IMAGEM = 'imagem';

    public function setting(): ?SocialSetting
    {
        return SocialSetting::current();
    }

    /**
     * Publica o post nas redes escolhidas e devolve o resultado por rede.
     *
     * @return array{
     *     facebook: array{status: string, id: ?string, erro: ?string, categoria: string},
     *     instagram: array{status: string, id: ?string, erro: ?string, categoria: string}
     * }
     */
    public function publicar(SocialPost $post, SocialSetting $setting): array
    {
        return [
            'facebook' => $this->publicarNoFacebook($post, $setting),
            'instagram' => $this->publicarNoInstagram($post, $setting),
        ];
    }

    /**
     * @return array{status: string, id: ?string, erro: ?string, categoria: string}
     */
    protected function publicarNoFacebook(SocialPost $post, SocialSetting $setting): array
    {
        if (! $post->publicaNoFacebook()) {
            return $this->ignorado();
        }

        if (! $setting->hasPageToken()) {
            return $this->erroResultado(self::CATEGORIA_SEM_CONFIGURACAO, 'Configure a Página do Facebook e o token de acesso antes de publicar.');
        }

        $base = $this->baseUrl($setting, $setting->facebook_page_id);

        try {
            if (filled($post->image_url)) {
                $response = $this->http()->post($base.'/photos', [
                    'url' => $post->image_url,
                    'caption' => $post->legendaPara('facebook'),
                    'access_token' => $setting->page_access_token,
                ]);
            } else {
                $response = $this->http()->post($base.'/feed', [
                    'message' => $post->legendaPara('facebook'),
                    'access_token' => $setting->page_access_token,
                ]);
            }
        } catch (Throwable $e) {
            return $this->erroResultado(self::CATEGORIA_CONEXAO, 'Não foi possível falar com a Meta agora. Tente novamente.');
        }

        return $this->interpretarResposta($response, 'facebook');
    }

    /**
     * O Instagram publica em dois passos: cria o contêiner da mídia e depois o
     * publica. A imagem precisa estar acessível publicamente em HTTPS.
     *
     * @return array{status: string, id: ?string, erro: ?string, categoria: string}
     */
    protected function publicarNoInstagram(SocialPost $post, SocialSetting $setting): array
    {
        if (! $post->publicaNoInstagram()) {
            return $this->ignorado();
        }

        if (! $setting->hasInstagram()) {
            return $this->erroResultado(self::CATEGORIA_SEM_CONFIGURACAO, 'Configure a conta do Instagram Business e o token de acesso antes de publicar.');
        }

        if (! $this->imagemPublicaHttps($post->image_url)) {
            return $this->erroResultado(self::CATEGORIA_IMAGEM, 'O Instagram exige uma imagem pública em HTTPS. Envie uma imagem para o post.');
        }

        $base = $this->baseUrl($setting, $setting->instagram_business_id);

        try {
            $criacao = $this->http()->post($base.'/media', [
                'image_url' => $post->image_url,
                'caption' => $post->legendaPara('instagram'),
                'access_token' => $setting->page_access_token,
            ]);

            $container = $this->interpretarResposta($criacao, 'instagram');

            if ($container['status'] !== SocialPost::REDE_PUBLICADO) {
                return $container;
            }

            $publicacao = $this->http()->post($base.'/media_publish', [
                'creation_id' => $container['id'],
                'access_token' => $setting->page_access_token,
            ]);
        } catch (Throwable $e) {
            return $this->erroResultado(self::CATEGORIA_CONEXAO, 'Não foi possível falar com a Meta agora. Tente novamente.');
        }

        return $this->interpretarResposta($publicacao, 'instagram');
    }

    /**
     * Lê o nome da Página e o Instagram vinculado, para o operador conferir se o
     * token e os IDs estão certos. Não publica nada.
     *
     * @return array{ok: bool, facebook_page_name: ?string, instagram_business_id: ?string, instagram_username: ?string, erro: ?string}
     */
    public function verificarConexao(SocialSetting $setting): array
    {
        if (! $setting->hasPageToken()) {
            return $this->resultadoVerificacao(false, erro: 'Informe a Página do Facebook e o token de acesso antes de verificar.');
        }

        try {
            $response = $this->http()->get($this->baseUrl($setting, $setting->facebook_page_id), [
                'fields' => 'name,instagram_business_account{id,username}',
                'access_token' => $setting->page_access_token,
            ]);
        } catch (Throwable $e) {
            return $this->resultadoVerificacao(false, erro: 'Não foi possível falar com a Meta agora. Tente novamente.');
        }

        $dados = $this->json($response);
        $erro = is_array($dados['error'] ?? null) ? $dados['error'] : [];

        if (! $response->successful() || $erro !== []) {
            $classificado = $this->classificarErro($response->status(), $erro, 'facebook');

            return $this->resultadoVerificacao(false, erro: $classificado['erro']);
        }

        $instagram = is_array($dados['instagram_business_account'] ?? null) ? $dados['instagram_business_account'] : [];

        return $this->resultadoVerificacao(
            ok: true,
            facebookPageName: $dados['name'] ?? null,
            instagramBusinessId: $instagram['id'] ?? null,
            instagramUsername: $instagram['username'] ?? null,
        );
    }

    /**
     * @return array{status: string, id: ?string, erro: ?string, categoria: string}
     */
    protected function interpretarResposta(Response $response, string $rede): array
    {
        $dados = $this->json($response);
        $erro = is_array($dados['error'] ?? null) ? $dados['error'] : [];

        if ($response->successful() && $erro === [] && filled($dados['id'] ?? null)) {
            return [
                'status' => SocialPost::REDE_PUBLICADO,
                'id' => (string) $dados['id'],
                'erro' => null,
                'categoria' => self::CATEGORIA_OK,
            ];
        }

        return $this->classificarErro($response->status(), $erro, $rede);
    }

    /**
     * @param  array<string, mixed>  $erro
     * @return array{status: string, id: ?string, erro: ?string, categoria: string}
     */
    protected function classificarErro(int $http, array $erro, string $rede): array
    {
        $codigo = (int) ($erro['code'] ?? 0);
        $subcodigo = (int) ($erro['error_subcode'] ?? 0);

        Log::warning('Publicação social da clínica recusada pela Meta.', [
            'rede' => $rede,
            'http_status' => $http,
            'codigo' => $codigo,
            'subcodigo' => $subcodigo,
            'tipo' => $erro['type'] ?? null,
        ]);

        if ($http === 401 || $http === 403 || in_array($codigo, [102, 190], true)) {
            return $this->erroResultado(self::CATEGORIA_AUTENTICACAO, 'O token de acesso é inválido ou expirou. Gere um novo Page Access Token na configuração.');
        }

        if ($http === 429 || in_array($codigo, [4, 17, 32, 613], true)) {
            return $this->erroResultado(self::CATEGORIA_LIMITE, 'A Meta limitou a publicação no momento. Aguarde alguns minutos e tente de novo.');
        }

        if (in_array($codigo, [10, 200, 272], true)) {
            return $this->erroResultado(self::CATEGORIA_PERMISSAO, 'A Página ou o Instagram não autorizou esta publicação. Confira as permissões do token (pages_manage_posts e instagram_content_publish).');
        }

        if ($http === 400 || $codigo === 100) {
            return $this->erroResultado(self::CATEGORIA_CONTEUDO, 'A Meta recusou o conteúdo do post (imagem ou legenda). Revise e tente novamente.');
        }

        if ($http >= 500) {
            return $this->erroResultado(self::CATEGORIA_META, 'A Meta está instável agora. Tente novamente em instantes.');
        }

        return $this->erroResultado(self::CATEGORIA_META, 'Não foi possível publicar no '.$this->rotulo($rede).'.');
    }

    public function imagemPublicaHttps(?string $url): bool
    {
        if (! filled($url)) {
            return false;
        }

        return ! Validator::make(['image_url' => $url], ['image_url' => [new PublicHttpsUrl]])->fails();
    }

    /**
     * @return array{status: string, id: ?string, erro: ?string, categoria: string}
     */
    protected function ignorado(): array
    {
        return [
            'status' => SocialPost::REDE_IGNORADO,
            'id' => null,
            'erro' => null,
            'categoria' => self::CATEGORIA_OK,
        ];
    }

    /**
     * @return array{status: string, id: ?string, erro: ?string, categoria: string}
     */
    protected function erroResultado(string $categoria, string $mensagem): array
    {
        return [
            'status' => SocialPost::REDE_ERRO,
            'id' => null,
            'erro' => $mensagem,
            'categoria' => $categoria,
        ];
    }

    /**
     * @return array{ok: bool, facebook_page_name: ?string, instagram_business_id: ?string, instagram_username: ?string, erro: ?string}
     */
    protected function resultadoVerificacao(
        bool $ok,
        ?string $facebookPageName = null,
        ?string $instagramBusinessId = null,
        ?string $instagramUsername = null,
        ?string $erro = null,
    ): array {
        return [
            'ok' => $ok,
            'facebook_page_name' => $facebookPageName,
            'instagram_business_id' => $instagramBusinessId,
            'instagram_username' => $instagramUsername,
            'erro' => $erro,
        ];
    }

    protected function baseUrl(SocialSetting $setting, string $id): string
    {
        return 'https://graph.facebook.com/'.$setting->graphVersion().'/'.$id;
    }

    protected function http(): PendingRequest
    {
        return Http::timeout((int) config('services.social_publish.timeout', 30))->acceptJson();
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        try {
            $dados = $response->json();
        } catch (Throwable) {
            return [];
        }

        return is_array($dados) ? $dados : [];
    }

    protected function rotulo(string $rede): string
    {
        return $rede === 'instagram' ? 'Instagram' : 'Facebook';
    }
}
