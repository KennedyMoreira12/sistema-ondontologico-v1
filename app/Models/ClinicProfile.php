<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Perfil operacional da clínica: razão social, CNPJ, contatos, endereço e
 * marca. Fica no banco da clínica — o central só precisa do mínimo para
 * rotear e faturar.
 *
 * Tabela de linha única (id 1). Use `ClinicProfile::current()` em vez de
 * `first()`, para não depender de ordering em lugar nenhum.
 */
#[Fillable([
    'legal_name',
    'document',
    'phone',
    'whatsapp',
    'postal_code',
    'address',
    'number',
    'complement',
    'district',
    'city',
    'state',
    'logo_path',
    'primary_color',
    'secondary_color',
])]
class ClinicProfile extends TenantModel
{
    /**
     * Singular de propósito: a tabela tem uma linha só, a da clínica deste
     * banco. Sem isso o Eloquent pluralizaria para `clinic_profiles`.
     */
    protected $table = 'clinic_profile';

    /**
     * A linha da clínica, criando se ainda não existir.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], []);
    }
}
