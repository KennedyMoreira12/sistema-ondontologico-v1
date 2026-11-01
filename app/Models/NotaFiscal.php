<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento fiscal emitido para um paciente/atendimento.
 *
 * Os status formam um ciclo curto:
 *
 *   rascunho -> processando -> autorizada -> cancelada
 *                    \-> rejeitada -> (nova tentativa) -> processando
 *
 * `processando` existe porque provedores como PlugNotas e Focus respondem de
 * forma assíncrona; o comando `fiscal:sincronizar` resolve esses casos.
 */
#[Fillable([
    'paciente_id', 'consulta_id', 'numero', 'serie', 'chave', 'status', 'tipo',
    'valor_total', 'valor_icms', 'dados', 'emitida_em', 'cancelada_em',
    'motivo_cancelamento', 'link_pdf', 'link_xml',
    'ambiente', 'provider', 'protocolo', 'motivo_rejeicao', 'tentativas',
    'ultima_tentativa_em', 'valor_desconto', 'valor_liquido', 'valor_iss',
    'aliquota_iss', 'codigo_servico', 'descricao_servico',
    'tomador_nome', 'tomador_documento', 'tomador_email', 'tomador_endereco',
    'tomador_cidade', 'tomador_uf', 'tomador_cep',
])]
class NotaFiscal extends TenantModel
{
    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_PROCESSANDO = 'processando';

    public const STATUS_AUTORIZADA = 'autorizada';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS_REJEITADA = 'rejeitada';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_RASCUNHO => 'Rascunho',
        self::STATUS_PROCESSANDO => 'Processando',
        self::STATUS_AUTORIZADA => 'Autorizada',
        self::STATUS_CANCELADA => 'Cancelada',
        self::STATUS_REJEITADA => 'Rejeitada',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_CLASSES = [
        self::STATUS_RASCUNHO => 'secondary',
        self::STATUS_PROCESSANDO => 'info',
        self::STATUS_AUTORIZADA => 'success',
        self::STATUS_CANCELADA => 'dark',
        self::STATUS_REJEITADA => 'danger',
    ];

    protected $table = 'notas_fiscais';

    /**
     * `tentativas` tem default no banco, mas o model recém-criado ficaria com
     * null na resposta da API até um refresh. O default aqui mantém a leitura
     * coerente sem custar uma query.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tentativas' => 0,
    ];

    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'valor_icms' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'valor_liquido' => 'decimal:2',
            'valor_iss' => 'decimal:2',
            'aliquota_iss' => 'decimal:2',
            'tentativas' => 'integer',
            'dados' => 'array',
            'emitida_em' => 'datetime',
            'cancelada_em' => 'datetime',
            'ultima_tentativa_em' => 'datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /**
     * O agendamento que originou a nota. A coluna nasceu como `consulta_id`
     * e é mantida para não quebrar dados existentes.
     */
    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'consulta_id');
    }

    public function emitida(): bool
    {
        return $this->status === self::STATUS_AUTORIZADA;
    }

    public function podeEmitir(): bool
    {
        return in_array($this->status, [self::STATUS_RASCUNHO, self::STATUS_REJEITADA], true);
    }

    public function podeCancelar(): bool
    {
        return $this->status === self::STATUS_AUTORIZADA;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusClass(): string
    {
        return self::STATUS_CLASSES[$this->status] ?? 'secondary';
    }

    public function valorLiquido(): float
    {
        return (float) ($this->valor_liquido ?? max(0, (float) $this->valor_total - (float) $this->valor_desconto));
    }

    public function scopeAutorizadas(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AUTORIZADA);
    }

    public function scopeDoPeriodo(Builder $query, ?string $de, ?string $ate): Builder
    {
        return $query
            ->when($de, fn (Builder $q, $data) => $q->whereDate('created_at', '>=', $data))
            ->when($ate, fn (Builder $q, $data) => $q->whereDate('created_at', '<=', $data));
    }

    /**
     * Próximo número da série, com 9 dígitos.
     *
     * Soma sobre os números já usados em vez de confiar em auto-incremento:
     * o número fiscal é sequencial por emitente e não pode ter buraco. O
     * `lockForUpdate` segura a faixa até o commit, para duas emissões
     * simultâneas não receberem o mesmo número.
     */
    public static function proximoNumero(): string
    {
        $ultimo = static::query()
            ->whereNotNull('numero')
            ->lockForUpdate()
            ->pluck('numero')
            ->map(fn ($numero) => (int) $numero)
            ->max() ?? 0;

        return str_pad((string) ($ultimo + 1), 9, '0', STR_PAD_LEFT);
    }
}
