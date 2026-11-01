<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Contas de Instagram e Facebook DA CLÍNICA.
 *
 * Linha única (o serviço sempre usa a primeira), separada dos posts de
 * propósito. O `page_access_token` é gravado cifrado pelo cast `encrypted` —
 * nunca em claro no banco, e nunca devolvido para a tela.
 *
 * Não confundir com App\Models\Central\PlatformSocialSetting, que guarda as
 * contas da plataforma (a LJ) no banco central.
 */
#[Fillable([
    'facebook_page_id', 'facebook_page_name',
    'instagram_business_id', 'instagram_username',
    'page_access_token', 'graph_version', 'verified_at', 'last_error',
])]
class SocialSetting extends TenantModel
{
    /**
     * O pluralizador do Laravel geraria `social_settings` de qualquer forma, mas
     * o nome fica explícito para casar com a migration.
     */
    protected $table = 'social_settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_access_token' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function hasPageToken(): bool
    {
        return filled($this->page_access_token) && filled($this->facebook_page_id);
    }

    public function hasInstagram(): bool
    {
        return filled($this->page_access_token) && filled($this->instagram_business_id);
    }

    /**
     * A versão salva vence; sem ela, vale o padrão da configuração.
     */
    public function graphVersion(): string
    {
        return $this->graph_version ?: (string) config('services.social_publish.graph_version', 'v23.0');
    }
}
