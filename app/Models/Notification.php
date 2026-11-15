<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Notificação da clínica.
 *
 * É o fato que interessa à clínica inteira. Quem leu e quando fica em
 * `NotificationRead`, para que o alerta seja criado uma vez e cada usuário
 * marque o seu.
 */
#[Fillable([
    'categoria', 'tipo', 'prioridade', 'titulo', 'mensagem', 'url', 'permissao',
    'referencia_type', 'referencia_id', 'dedupe_key', 'origem', 'expira_em',
    'resolved_at',
])]
class Notification extends TenantModel
{
    public const CATEGORIA_FINANCEIRO = 'financeiro';

    public const CATEGORIA_ESTOQUE = 'estoque';

    public const CATEGORIA_AGENDA = 'agenda';

    public const CATEGORIA_PACIENTES = 'pacientes';

    public const CATEGORIA_ASSINATURAS = 'assinaturas';

    /**
     * Categorias na ordem em que o menu de preferências e o histórico mostram.
     *
     * @var list<string>
     */
    public const CATEGORIAS = [
        self::CATEGORIA_FINANCEIRO,
        self::CATEGORIA_ESTOQUE,
        self::CATEGORIA_AGENDA,
        self::CATEGORIA_PACIENTES,
        self::CATEGORIA_ASSINATURAS,
    ];

    public const PRIORIDADE_INFO = 'info';

    public const PRIORIDADE_AVISO = 'warning';

    public const PRIORIDADE_URGENTE = 'critical';

    /**
     * @var list<string>
     */
    public const PRIORIDADES = [
        self::PRIORIDADE_INFO,
        self::PRIORIDADE_AVISO,
        self::PRIORIDADE_URGENTE,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function leituras(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    /**
     * Notificações que ainda pedem atenção.
     *
     * Resolvida (a conta foi paga, o estoque foi reposto) sai da lista; expirada
     * (a sessão já começou) também. O histórico continua acessível pela página.
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query
            ->whereNull('resolved_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expira_em')->orWhere('expira_em', '>', now());
            });
    }

    public function scopeDaCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('categoria', $categoria);
    }

    public function estaResolvida(): bool
    {
        return $this->resolved_at !== null;
    }

    public function expirou(): bool
    {
        return $this->expira_em !== null && $this->expira_em->isPast();
    }

    /**
     * Rótulo da prioridade para a tela.
     */
    public function rotuloPrioridade(): string
    {
        return match ($this->prioridade) {
            self::PRIORIDADE_URGENTE => 'Urgente',
            self::PRIORIDADE_AVISO => 'Atenção',
            default => 'Informação',
        };
    }

    public function icone(): string
    {
        return match ($this->categoria) {
            self::CATEGORIA_FINANCEIRO => 'bi-cash-coin',
            self::CATEGORIA_ESTOQUE => 'bi-box-seam',
            self::CATEGORIA_AGENDA => 'bi-calendar-event',
            self::CATEGORIA_PACIENTES => 'bi-person-lines-fill',
            self::CATEGORIA_ASSINATURAS => 'bi-pen',
            default => 'bi-bell',
        };
    }
}
