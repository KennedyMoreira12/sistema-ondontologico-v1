<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gera o conteúdo de um post social pelo workflow n8n.
 *
 * O Laravel não fala com o modelo nem grava o post: ele entrega os dados do
 * formulário ao webhook do workflow (LJSocialAI01), que chama a IA e só então
 * cria o SocialPost pela API interna de posts (`/api/social/posts`). Quem
 * autentica essa criação é a credencial de integração que já existe no n8n — o
 * formulário web não envia token algum, e este serviço só repassa os campos que
 * o operador digitou.
 *
 * A resposta do webhook é sempre JSON com um campo booleano `success`. Erro de
 * validação de entrada ou resposta inválida da IA chega com HTTP 200 e
 * `success: false`, por isso `successful()` sozinho não diz se deu certo.
 */
class SocialContentGenerator
{
    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, post_id: int|null, error: ?string}
     */
    public function generate(array $dados): array
    {
        $url = trim((string) config('services.social_ai.webhook_url'));

        if ($url === '') {
            return $this->falha('O serviço de geração de conteúdo não está configurado.');
        }

        try {
            $response = Http::timeout((int) config('services.social_ai.timeout', 60))
                ->acceptJson()
                ->post($url, $dados);
        } catch (ConnectionException $exception) {
            Log::warning('Falha ao chamar o gerador de conteúdo social.', [
                'motivo' => $exception->getMessage(),
            ]);

            return $this->falha('Não foi possível falar com o serviço de geração de conteúdo. Tente novamente.');
        }

        if (! $response->successful()) {
            Log::warning('Gerador de conteúdo social respondeu erro.', [
                'status' => $response->status(),
                'corpo' => mb_substr($response->body(), 0, 500),
            ]);

            return $this->falha('O serviço de geração de conteúdo retornou um erro. Tente novamente.');
        }

        $json = $response->json();

        if (! is_array($json) || ($json['success'] ?? false) !== true) {
            $erro = is_array($json) && is_string($json['error'] ?? null)
                ? $json['error']
                : 'Não foi possível gerar o conteúdo.';

            return $this->falha(mb_substr($erro, 0, 300));
        }

        $postId = $json['post']['id'] ?? null;

        if (! is_int($postId) && ! (is_string($postId) && ctype_digit($postId))) {
            Log::warning('Gerador de conteúdo social não devolveu o id do post.', [
                'resposta' => mb_substr($response->body(), 0, 500),
            ]);

            return $this->falha('O conteúdo foi gerado, mas o post não pôde ser identificado. Confira a lista de publicações.');
        }

        return ['ok' => true, 'post_id' => (int) $postId, 'error' => null];
    }

    /**
     * @return array{ok: bool, post_id: null, error: string}
     */
    private function falha(string $error): array
    {
        return ['ok' => false, 'post_id' => null, 'error' => $error];
    }
}
