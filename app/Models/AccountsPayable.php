<?php

namespace App\Models;

use App\Models\Central\User;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta a pagar da clínica.
 *
 * `status` guarda a decisão registrada (pendente, pago ou cancelado). O
 * "atrasado" NÃO é gravado: ele é calculado pela data de vencimento e pelo
 * saldo, porque um status gravado envelhece sozinho — a conta vence à
 * meia-noite e ninguém roda um job para reescrever cada linha.
 */
#[Fillable([
    'supplier_id', 'financeiro_categoria_id', 'nome_conta', 'descricao',
    'valor', 'data_vencimento', 'data_pagamento', 'status', 'tipo',
    'tipo_documento', 'observacoes',
])]
class AccountsPayable extends TenantModel
{
    protected $table = 'accounts_payable';

    public const PENDENTE = 'pendente';

    public const PAGO = 'pago';

    public const CANCELADO = 'cancelado';

    public const ATRASADO = 'atrasado';

    public const TIPOS = [
        'fornecedor', 'energia', 'agua', 'aluguel', 'emprestimo', 'servico', 'outros',
    ];

    public const TIPOS_DOCUMENTO = ['nf', 'boleto', 'duplicata', 'outros'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data_vencimento' => 'date',
            'data_pagamento' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financeiro_categoria_id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(AccountsPayablePayment::class);
    }

    /**
     * Soma das baixas registradas.
     *
     * Quando a listagem usa `withSum`, a soma já vem no atributo e não há uma
     * consulta por linha.
     */
    public function getValorPagoAttribute(): float
    {
        if (array_key_exists('pagamentos_sum_valor', $this->attributes)) {
            return round((float) $this->attributes['pagamentos_sum_valor'], 2);
        }

        if ($this->relationLoaded('pagamentos')) {
            return round((float) $this->pagamentos->sum('valor'), 2);
        }

        return round((float) $this->pagamentos()->sum('valor'), 2);
    }

    public function getSaldoAttribute(): float
    {
        return round(max(0, (float) $this->valor - $this->valor_pago), 2);
    }

    public function estaPaga(): bool
    {
        return $this->status === self::PAGO || $this->saldo <= 0;
    }

    public function estaAtrasada(): bool
    {
        return ! $this->estaPaga()
            && $this->status !== self::CANCELADO
            && $this->data_vencimento !== null
            && $this->data_vencimento->lt(today());
    }

    /**
     * Status que a tela mostra: o gravado, enriquecido com o atraso calculado.
     */
    public function statusEfetivo(): string
    {
        if ($this->status === self::CANCELADO) {
            return self::CANCELADO;
        }

        if ($this->estaPaga()) {
            return self::PAGO;
        }

        return $this->estaAtrasada() ? self::ATRASADO : self::PENDENTE;
    }

    /**
     * Expressão SQL do saldo devedor (valor da conta menos baixas).
     *
     * Fica no model para que listagem, resumo e fluxo de caixa usem a mesma
     * regra: uma conta parcialmente paga continua devendo a diferença, e não
     * o valor cheio.
     */
    public static function expressaoSaldo(): string
    {
        return 'valor - COALESCE((SELECT SUM(valor) FROM accounts_payable_pagamentos '
            .'WHERE accounts_payable_pagamentos.accounts_payable_id = accounts_payable.id), 0)';
    }

    public function scopeEmAberto(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', self::CANCELADO)
            ->where('status', '!=', self::PAGO);
    }

    public function scopeVencendoAte(Builder $query, mixed $data): Builder
    {
        return $query->whereDate('data_vencimento', '<=', $data);
    }

    public function scopeDoTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Usuário responsável pela última baixa (para a tela de detalhe).
     */
    public function usuarioDaBaixa(AccountsPayablePayment $pagamento): ?User
    {
        return $pagamento->user;
    }
}
