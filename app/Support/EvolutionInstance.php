<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * O nome da instância da Evolution API que atende uma clínica.
 *
 * A instância é a chave com que o n8n descobre a dona de uma mensagem: o
 * webhook traz o nome da instância e o `CLINICAS_MAP` troca por URL da API e
 * token daquela clínica. Um nome divergente — uma letra maiúscula a mais, um
 * espaço — é silencioso: o n8n simplesmente não acha a clínica e a mensagem é
 * descartada sem erro visível.
 *
 * Por isso o formato é fechado e em minúsculas, e a regra vive aqui em vez de
 * ser repetida nas duas telas do painel.
 */
final class EvolutionInstance
{
    /**
     * Minusúsculas, dígitos, ponto, hífen e underscore; começa por letra ou
     * dígito. 60 caracteres é o máximo do campo e do nome de instância.
     */
    public const PATTERN = '/^[a-z0-9][a-z0-9._-]{0,59}$/';

    /**
     * Regras do campo nos formulários do painel.
     *
     * @return array<int, mixed>
     */
    public static function rules(?int $ignoreId = null): array
    {
        $unique = Rule::unique('clinics', 'evolution_instance');

        if ($ignoreId !== null) {
            $unique = $unique->ignore($ignoreId);
        }

        return [
            'nullable',
            'string',
            'max:60',
            'regex:'.self::PATTERN,
            $unique,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'evolution_instance.regex' => 'Use apenas letras minúsculas, números, ponto, hífen ou underscore, começando por letra ou número.',
            'evolution_instance.unique' => 'Esta instância da Evolution já pertence a outra clínica.',
        ];
    }

    /**
     * Minusculiza e apara o que o operador digitou, e transforma string vazia
     * em `null` — senão o índice único do MySQL trata duas clínicas sem
     * instância como duplicada.
     */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return $value === '' ? null : $value;
    }

    /**
     * Nome sugerido para a instância de uma clínica nova.
     *
     * O `id` entra porque nome de clínica se repete — duas "Clínica Sorriso" em
     * cidades diferentes são plausíveis — e a Evolution recusa instância
     * duplicada. O slug entra para o operador reconhecer a instância no painel
     * da Evolution sem consultar o banco.
     *
     * O resultado respeita `PATTERN`: minúsculas, começa por letra, no máximo 60
     * caracteres. A unicidade final é conferida pelo provisionador, que conhece
     * as outras clínicas.
     */
    public static function suggest(int $clinicId, string $clinicName): string
    {
        $slug = Str::slug($clinicName);
        $base = 'clinica-'.$clinicId.($slug !== '' ? '-'.$slug : '');

        return rtrim(mb_substr($base, 0, 60), '-._');
    }
}
