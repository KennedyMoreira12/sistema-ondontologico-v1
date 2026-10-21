<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Post próprio da plataforma (a LJ) para Facebook e/ou Instagram.
 *
 * Não confundir com App\Models\SocialPost, que é o post de uma clínica no banco
 * do tenant. Este vive no banco central e é publicado pelas contas da LJ.
 */
#[Fillable([
    'admin_user_id', 'titulo', 'legenda_instagram', 'legenda_facebook',
    'hashtags', 'image_url', 'image_path', 'platforms', 'status',
    'facebook_status', 'instagram_status', 'facebook_post_id',
    'instagram_media_id', 'error', 'payload', 'published_at',
])]
class PlatformSocialPost extends CentralModel
{
    protected $table = 'platform_social_posts';

    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_PUBLICADO = 'publicado';
    public const STATUS_PARCIAL = 'parcial';
    public const STATUS_ERRO = 'erro';

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
            'published_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
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

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLICADO => 'Publicado',
            self::STATUS_PARCIAL => 'Parcial',
            self::STATUS_ERRO => 'Erro',
            default => 'Rascunho',
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLICADO => 'success',
            self::STATUS_PARCIAL => 'warning',
            self::STATUS_ERRO => 'danger',
            default => 'secondary',
        };
    }
}
