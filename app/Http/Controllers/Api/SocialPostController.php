<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveSocialPostRequest;
use App\Http\Requests\RejectSocialPostRequest;
use App\Http\Requests\StoreSocialPostImageRequest;
use App\Http\Requests\StoreSocialPostRequest;
use App\Models\SocialPost;
use App\Services\SocialImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialPostController extends Controller
{
    public function __construct(private SocialImageStorage $images) {}

    public function index(Request $request): JsonResponse
    {
        $posts = SocialPost::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('platform'), fn ($query) => $query->whereJsonContains('platforms', $request->input('platform')))
            ->when($request->filled('scheduled_at'), fn ($query) => $query->whereDate('scheduled_at', $request->input('scheduled_at')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json($posts);
    }

    public function store(StoreSocialPostRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $post = SocialPost::create([
            ...$validated,
            'status' => 'pendente',
            'facebook_status' => 'pendente',
            'instagram_status' => 'pendente',
            'approved_at' => null,
            'published_at' => null,
        ]);

        return response()->json(['post' => $post], 201);
    }

    /**
     * Recebe a imagem que o workflow gerou no OpenRouter e grava no disco público.
     *
     * O n8n chama este endpoint uma única vez por post, logo depois de criar o
     * registro: no sucesso vem o arquivo; quando a geração falha, vem o texto do
     * erro sem arquivo. Na falha o post continua como está — conteúdo aprovável,
     * apenas sem imagem — em vez de ser descartado.
     */
    public function storeImage(StoreSocialPostImageRequest $request, int $id): JsonResponse
    {
        $post = SocialPost::findOrFail($id);
        $payload = $post->payload ?? [];

        if ($request->filled('error')) {
            $payload['image_generation'] = array_merge($payload['image_generation'] ?? [], [
                'provider' => 'openrouter',
                'model' => $request->validated('model'),
                'error' => (string) $request->input('error'),
            ]);

            $post->update(['payload' => $payload]);

            return response()->json([
                'post' => $post,
                'image_error' => $payload['image_generation']['error'],
            ]);
        }

        $arquivo = $request->file('image');

        if ($arquivo === null) {
            return response()->json(['message' => 'Envie a imagem gerada ou o erro da geração.'], 422);
        }

        if ($post->image_url !== null) {
            return response()->json(['message' => 'Este post já possui imagem. Gere outra antes de enviar.'], 422);
        }

        $salvo = $this->images->store($arquivo);

        if ($salvo === null) {
            return response()->json(['message' => 'Não foi possível armazenar a imagem gerada.'], 500);
        }

        if (! $this->images->urlIsPublicHttps($salvo['url'])) {
            $this->images->delete($salvo['path']);

            return response()->json(['message' => 'A URL pública da imagem gerada não é HTTPS. Confira o APP_URL.'], 422);
        }

        $geracao = array_merge($payload['image_generation'] ?? [], [
            'provider' => 'openrouter',
            'model' => $request->validated('model'),
            'cost_usd' => $request->filled('cost_usd') ? (float) $request->input('cost_usd') : null,
            'media_type' => $salvo['mime'],
        ]);

        unset($geracao['error']);

        $post->update([
            'image_url' => $salvo['url'],
            'payload' => array_merge($payload, ['image_generation' => $geracao]),
        ]);

        return response()->json(['post' => $post, 'image_url' => $salvo['url']]);
    }

    public function show(int $id): JsonResponse
    {
        $post = SocialPost::findOrFail($id);

        return response()->json(['post' => $post]);
    }

    public function approve(ApproveSocialPostRequest $request, int $id): JsonResponse
    {
        $post = SocialPost::findOrFail($id);

        if ($post->status !== 'pendente') {
            return response()->json(['message' => 'Somente publicações pendentes podem ser aprovadas.'], 422);
        }

        $post->update([
            'status' => 'aprovado',
            'approved_at' => now(),
        ]);

        return response()->json(['post' => $post]);
    }

    public function reject(RejectSocialPostRequest $request, int $id): JsonResponse
    {
        $post = SocialPost::findOrFail($id);

        if ($post->status !== 'pendente') {
            return response()->json(['message' => 'Somente publicações pendentes podem ser rejeitadas.'], 422);
        }

        $data = [
            'status' => 'rejeitado',
            'approved_at' => null,
            'published_at' => null,
        ];

        if ($request->filled('motivo')) {
            $data['payload'] = array_merge($post->payload ?? [], ['rejection_reason' => $request->input('motivo')]);
        }

        $post->update($data);

        return response()->json(['post' => $post]);
    }
}
