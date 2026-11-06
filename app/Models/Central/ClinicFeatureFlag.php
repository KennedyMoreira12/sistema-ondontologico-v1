<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Uma flag de recurso ligada/desligada para uma clínica.
 *
 * A linha só existe quando alguém decidiu explicitamente. "Não existe" é
 * diferente de "existe desligada": o primeiro usa o padrão do config, o
 * segundo registra uma decisão de operador (e aparece no painel).
 */
#[Fillable(['clinic_id', 'flag', 'enabled'])]
class ClinicFeatureFlag extends CentralModel
{
    protected $table = 'clinic_feature_flags';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
