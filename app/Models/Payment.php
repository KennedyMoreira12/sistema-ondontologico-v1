<?php

namespace App\Models;

use App\Models\Central\User;
use App\Models\InsurancePlan;
use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'installment_id', 'accounts_receivable_id', 'payment_method_id', 'user_id',
    'paciente_id', 'servico', 'insurance_plan_id', 'origem', 'data', 'valor',
    'observacoes',
])]
class Payment extends TenantModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function accountsReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountsReceivable::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function insurancePlan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class, 'insurance_plan_id');
    }

    /**
     * Paciente dono do pagamento.
     *
     * É anulável de propósito: os 6.163 lançamentos importados da planilha
     * vieram só com o nome no texto e são vinculados por revisão humana. Um
     * pagamento sem vínculo continua existindo e continua somando no caixa.
     */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSemPaciente(Builder $query): Builder
    {
        return $query->whereNull('paciente_id');
    }
}
