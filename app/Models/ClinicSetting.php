<?php

namespace App\Models;

use App\Models\Tenant\TenantModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Configuração chave/valor da clínica.
 *
 * A tabela existe desde o começo e não tinha model. É o lugar natural para o
 * expediente e para os textos do atendimento: configuração operacional da
 * clínica, não da plataforma, e por isso no banco dela.
 *
 * `value` é texto livre; quem lê é que interpreta. Para JSON use
 * `ConfiguracaoWhatsApp`, que concentra o parse e o padrão de cada chave.
 */
#[Fillable(['key', 'value', 'grupo'])]
class ClinicSetting extends TenantModel
{
    protected $table = 'clinic_settings';

    public static function valor(string $chave, ?string $padrao = null): ?string
    {
        $registro = static::query()->where('key', $chave)->first();

        return $registro?->value ?? $padrao;
    }

    public static function definir(string $chave, ?string $valor, ?string $grupo = null): self
    {
        return static::query()->updateOrCreate(
            ['key' => $chave],
            ['value' => $valor, 'grupo' => $grupo ?? static::query()->where('key', $chave)->value('grupo')],
        );
    }
}
