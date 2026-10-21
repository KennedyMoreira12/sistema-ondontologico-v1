<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Contas de Instagram e Facebook da plataforma (a LJ).
 *
 * Linha única: o serviço sempre usa a primeira. É separada de `clinics` de
 * propósito, porque estas contas são da LJ e não de uma clínica.
 */
#[Fillable([
    'facebook_page_id', 'facebook_page_name',
    'instagram_business_id', 'instagram_username',
    'page_access_token', 'graph_version', 'verified_at', 'last_error',
])]
class PlatformSocialSetting extends CentralModel
{
    /**
     * O pluralizador do Laravel geraria `platform_social_settings` de qualquer
     * forma, mas o nome fica explícito para casar com a migration.
     */
    protected $table = 'platform_social_settings';

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
