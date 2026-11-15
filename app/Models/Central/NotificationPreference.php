<?php

namespace App\Models\Central;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferências de som e de notificação de um usuário.
 *
 * Vive no banco central porque o usuário também vive lá. A linha nasce na
 * primeira leitura, com os padrões do banco (sons ligados, volume 60, todas as
 * categorias), assim não é preciso criar preferência no cadastro do usuário.
 */
#[Fillable(['user_id', 'sons_ativos', 'volume', 'categorias', 'navegador_ativo'])]
class NotificationPreference extends CentralModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sons_ativos' => 'boolean',
            'volume' => 'integer',
            'categorias' => 'array',
            'navegador_ativo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function paraUsuario(User $user): self
    {
        return static::query()->firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Categoria ligada?
     *
     * `categorias` nulo significa "todas ligadas": uma categoria nova no
     * sistema não chega muda para quem nunca abriu a tela de preferências.
     */
    public function categoriaLigada(string $categoria): bool
    {
        $categorias = $this->categorias;

        if ($categorias === null) {
            return true;
        }

        return (bool) ($categorias[$categoria] ?? true);
    }

    /**
     * @return array<string, bool>
     */
    public function categoriasEfetivas(): array
    {
        $efetivas = [];

        foreach (Notification::CATEGORIAS as $categoria) {
            $efetivas[$categoria] = $this->categoriaLigada($categoria);
        }

        return $efetivas;
    }
}
