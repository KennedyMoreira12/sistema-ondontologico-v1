<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'external_id',
    'cnpj',
    'razao_social',
    'nome_fantasia',
    'inscricao_municipal',
    'inscricao_estadual',
    'regime_tributario',
    'codigo_cnae',
    'email',
    'phone',
    'postal_code',
    'address',
    'number',
    'complement',
    'district',
    'city',
    'state',
    'sendnf_company_id',
    'status',
    'last_check_at',
    'readiness_payload',
    'enabled',
    'enabled_at',
    'enabled_by_user_id',
])]

#[Hidden([
    'readiness_payload',
])]
class FiscalCompany extends TenantModel
{
    protected $table = 'fiscal_companies';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'last_check_at' => 'datetime',
            'readiness_payload' => 'array',
        ];
    }

    /**
     * Linha única da clínica deste tenant, criando se ainda não existir.
     * O tenant já é a clínica, então não há clinic_id nesta tabela.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['status' => 'not_configured']);
    }

    /**
     * A empresa pode emitir notas de verdade? Exige configuração global,
     * opt-in local, status pronto e empresa cadastrada na SendNF.
     */
    public function canEmitLocally(): bool
    {
        return $this->enabled
            && $this->status === 'ready'
            && filled($this->sendnf_company_id);
    }

    public function nfse(): HasMany
    {
        return $this->hasMany(Nfse::class);
    }
}
