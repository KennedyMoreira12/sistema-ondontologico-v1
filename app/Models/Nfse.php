<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'fiscal_company_id',
    'numero',
    'serie',
    'codigo_verificacao',
    'chave_acesso',
    'status',
    'tomador_tipo',
    'tomador_documento',
    'tomador_nome',
    'servico_codigo',
    'servico_descricao',
    'valor_servico',
    'valor_iss',
    'aliquota',
    'competencia',
    'data_emissao',
    'sendnf_id',
    'protocolo',
    'idempotency_key',
    'issued_by_user_id',
    'authorized_at',
    'rejected_reason',
    'request_hash',
    'response_sanitized',
])]
class Nfse extends TenantModel
{
    use SoftDeletes;

    protected $table = 'nfse';

    protected function casts(): array
    {
        return [
            'valor_servico' => 'decimal:2',
            'valor_iss' => 'decimal:2',
            'aliquota' => 'decimal:4',
            'competencia' => 'date',
            'data_emissao' => 'datetime',
            'authorized_at' => 'datetime',
            'response_sanitized' => 'array',
        ];
    }

    public function fiscalCompany(): BelongsTo
    {
        return $this->belongsTo(FiscalCompany::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(NfseEvent::class);
    }
}
