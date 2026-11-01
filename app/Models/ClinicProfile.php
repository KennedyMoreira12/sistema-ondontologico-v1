<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Perfil operacional da clínica: razão social, CNPJ, contatos, endereço,
 * marca e dados fiscais do emitente.
 *
 * Fica no banco da clínica — o central só precisa do mínimo para rotear e
 * faturar. Tabela de linha única (id 1): use `ClinicProfile::current()` em vez
 * de `first()`.
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
    'email',
    'state_registration',
    'municipal_registration',
    'cnae',
    'city_ibge_code',
    'tax_regime',
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

    /**
     * O emitente tem o mínimo para transmitir uma nota?
     *
     * Não valida dígito do CNPJ: isso é papel do provedor. Aqui só se evita
     * chamar a API com cadastro visivelmente incompleto.
     */
    public function emitenteConfigurado(): bool
    {
        $documento = preg_replace('/\D+/', '', (string) $this->document) ?? '';

        return filled($this->legal_name)
            && in_array(strlen($documento), [11, 14], true);
    }
}
