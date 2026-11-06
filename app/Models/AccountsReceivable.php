<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['paciente_id', 'budget_id', 'descricao', 'servico', 'vencimento', 'valor', 'valor_pago', 'desconto', 'status', 'observacoes'])]
class AccountsReceivable extends TenantModel
{
    protected $table = 'accounts_receivable';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vencimento' => 'date',
        ];
    }

    public function getSaldoAttribute(): float
    {
        return round(($this->valor - $this->valor_pago), 2);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
