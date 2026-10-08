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
}
