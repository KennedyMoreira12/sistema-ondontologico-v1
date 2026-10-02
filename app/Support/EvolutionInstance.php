<?php

namespace App\Support;

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
}
