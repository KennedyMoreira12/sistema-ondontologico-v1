<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'user_id',
    'tema',
    'objetivo',
    'publico',
    'tipo',
    'observacoes',
    'titulo',
    'legenda_instagram',
    'legenda_facebook',
    'cta',
    'hashtags',
    'prompt_imagem',
    'image_url',
    'platforms',
    'status',
    'facebook_status',
    'instagram_status',
    'scheduled_at',
    'approved_at',
    'published_at',
    'facebook_post_id',
    'instagram_media_id',
    'error',
    'payload',
])]
class SocialPost extends TenantModel
{
    protected $table = 'social_posts';

    /**
     * Estado de publicação POR REDE. É independente de `status`, que continua
     * sendo o estado de aprovação do fluxo (pendente/aprovado/rejeitado).
     */
    public const REDE_PENDENTE = 'pendente';
    public const REDE_PUBLICADO = 'publicado';
    public const REDE_ERRO = 'erro';
    public const REDE_IGNORADO = 'ignorado';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hashtags' => 'array',
            'platforms' => 'array',
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function redes(): array
    {
        return $this->platforms ?: [];
    }

    public function publicaNoFacebook(): bool
    {
        return in_array('facebook', $this->redes(), true);
    }

    public function publicaNoInstagram(): bool
    {
        return in_array('instagram', $this->redes(), true);
    }

    /**
     * A legenda da rede com as hashtags no fim, quando elas não vieram escritas
     * junto do texto.
     */
    public function legendaPara(string $rede): string
    {
        $legenda = $rede === 'instagram' ? $this->legenda_instagram : $this->legenda_facebook;

        $tags = collect($this->hashtags ?? [])
            ->map(fn (string $tag): string => str_starts_with($tag, '#') ? $tag : '#'.$tag)
            ->filter()
            ->implode(' ');

        if ($tags === '') {
            return trim((string) $legenda);
        }

        return trim((string) $legenda."\n\n".$tags);
    }
}
