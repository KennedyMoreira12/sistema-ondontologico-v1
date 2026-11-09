<?php

namespace App\Models;

use App\Models\Central\User;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma baixa (pagamento) de conta a pagar.
 *
 * O extrato é a fonte da verdade do valor pago: a conta pode ser quitada em
 * partes, e cada parte tem data, forma de pagamento e responsável.
 */
#[Fillable([
    'accounts_payable_id', 'payment_method_id', 'user_id',
    'idempotency_key', 'data', 'valor', 'juros', 'multa', 'observacoes',
])]
class AccountsPayablePayment extends TenantModel
{
    protected $table = 'accounts_payable_pagamentos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'juros' => 'decimal:2',
            'multa' => 'decimal:2',
        ];
    }

    /**
     * Quanto saiu do caixa nesta baixa: principal + encargos informados.
     *
     * `valor` é o que abate o saldo da conta; juros e multa são acréscimos
     * registrados ao lado, sem alterar a dívida original.
     */
    public function totalDesembolsado(): float
    {
        return round((float) $this->valor + (float) $this->juros + (float) $this->multa, 2);
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class, 'accounts_payable_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
