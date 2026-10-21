<?php

namespace App\Services;

use App\Rules\PublicHttpsUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Grava a imagem de um post social no disco público.
 *
 * É o mesmo caminho usado pelas duas entradas de imagem: o endpoint interno que
 * o n8n chama depois de gerar no OpenRouter e o upload manual feito pelo painel.
 * Centralizar aqui garante que o nome do arquivo, a pasta por mês e a trava de
 * URL HTTPS sejam idênticos nos dois casos.
 */
class SocialImageStorage
{
    /**
     * Extensão conferida a partir do MIME real — nunca do nome enviado.
     *
     * @var array<string, string>
     */
    private const EXTENSOES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{path: string, url: string, mime: string}|null
     */
    public function store(UploadedFile $arquivo): ?array
    {
        $mime = (string) $arquivo->getMimeType();
        $extensao = self::EXTENSOES[$mime] ?? 'bin';
        $disco = Storage::disk('public');

        try {
            $caminho = $disco->putFileAs(
                'social/'.now()->format('Y/m'),
                $arquivo,
                Str::uuid()->toString().'.'.$extensao
            );
        } catch (\Throwable) {
            return null;
        }

        if ($caminho === false || $caminho === null) {
            return null;
        }

        return ['path' => $caminho, 'url' => $disco->url($caminho), 'mime' => $mime];
    }

    public function delete(string $caminho): void
    {
        Storage::disk('public')->delete($caminho);
    }

    public function urlIsPublicHttps(string $url): bool
    {
        return ! Validator::make(
            ['image_url' => $url],
            ['image_url' => ['required', new PublicHttpsUrl]]
        )->fails();
    }
}
