<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nome', 'documento', 'email', 'phone', 'contato', 'endereco',
    'tipo_documento', 'observacoes', 'is_active',
])]
class Supplier extends TenantModel
{
    public const TIPOS_DOCUMENTO = ['nf', 'boleto', 'duplicata', 'outros'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Contas a pagar do fornecedor.
     *
     * O fornecedor é quem recebe; o status (pago/atrasado) é de cada conta, e
     * não do cadastro. Um fornecedor com uma conta paga e outra em aberto
     * continuaria aparecendo como "pago" se o status morasse aqui.
     */
    public function contasPayable(): HasMany
    {
        return $this->hasMany(AccountsPayable::class);
    }
}
