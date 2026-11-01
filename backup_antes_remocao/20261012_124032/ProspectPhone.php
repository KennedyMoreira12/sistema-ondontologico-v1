<?php

namespace App\Services\Platform\Prospeccao;

use Illuminate\Support\Str;

/**
 * Normaliza e classifica telefones brasileiros das listas de prospecção.
 *
 * Regras (DDD válido + comprimento/prefixo coerentes). Nunca "adivinha" número
 * ausente: sem dígitos confiáveis o resultado é `unconfirmed`/`incomplete`/
 * `invalid`, e o operador decide na revisão.
 */
class ProspectPhone
{
    /**
     * DDDs realmente atribuídos no Brasil. Um "(08)" ou "(03)" da lista não
     * passa por aqui — vira `invalid` em vez de virar um wa.me quebrado.
     *
     * @var array<int, int>
     */
    private const DDDS_VALIDOS = [
        11, 12, 13, 14, 15, 16, 17, 18, 19,
        21, 22, 24, 27, 28,
        31, 32, 33, 34, 35, 37, 38,
        41, 42, 43, 44, 45, 46, 47, 48, 49,
        51, 53, 54, 55,
        61, 62, 63, 64, 65, 66, 67, 68, 69,
        71, 73, 74, 75, 77, 79,
        81, 82, 83, 84, 85, 86, 87, 88, 89,
        91, 92, 93, 94, 95, 96, 97, 98, 99,
    ];

    /**
     * @return array{original:?string,normalized:?string,alt:?string,status:string,reason:?string}
     */
    public function analyze(?string $original): array
    {
        $raw = trim((string) $original);

        if ($raw === '') {
            return $this->result($original, null, null, 'unconfirmed', 'sem telefone na origem');
        }

        if ($this->semNumero($raw)) {
            return $this->result($original, null, null, 'unconfirmed', 'origem marca o telefone como não confirmado');
        }

        $parts = preg_split('/\s*\/\s*/', $raw) ?: [$raw];
        $primary = $this->digits($parts[0]);

        $alt = null;
        if (isset($parts[1]) && $parts[1] !== '') {
            $alt = $this->digits($parts[1]);
            // O segundo número da mesma linha costuma vir sem DDD.
            if (strlen($primary) >= 10 && strlen($alt) >= 8 && strlen($alt) <= 9) {
                $alt = substr($primary, 0, 2).$alt;
            }
        }

        [$primary, $status, $reason] = $this->classify($primary);

        $normalized = null;
        if (($status === 'valid' || $status === 'incomplete') && strlen($primary) >= 10) {
            $normalized = '55'.$primary;
        }

        return $this->result($original, $normalized, $this->normalizeAlt($alt), $status, $reason);
    }

    /**
     * @return array{0:string,1:string,2:?string}
     */
    private function classify(string $digits): array
    {
        if ($digits === '') {
            return [$digits, 'invalid', 'telefone sem dígitos'];
        }

        $len = strlen($digits);

        // Código do país já presente.
        if (($len === 12 || $len === 13) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
            $len = strlen($digits);
        }

        if ($len < 10) {
            return [$digits, 'incomplete', 'número sem DDD ou incompleto'];
        }

        if ($len > 11) {
            return [$digits, 'invalid', 'mais dígitos que o esperado'];
        }

        $ddd = (int) substr($digits, 0, 2);
        if (! in_array($ddd, self::DDDS_VALIDOS, true)) {
            return [$digits, 'invalid', 'DDD '.$ddd.' inexistente'];
        }

        $sub = substr($digits, 2);

        if ($len === 11) {
            if ($sub[0] !== '9') {
                return [$digits, 'invalid', 'celular de 11 dígitos sem o 9'];
            }

            return [$digits, 'valid', null];
        }

        // 10 dígitos: fixo válido se o prefixo for de fixo; celular truncado, não.
        if (in_array($sub[0], ['1', '6', '7', '8', '9'], true)) {
            return [$digits, 'incomplete', '10 dígitos com prefixo de celular'];
        }

        return [$digits, 'valid', null];
    }

    private function normalizeAlt(?string $alt): ?string
    {
        if ($alt === null) {
            return null;
        }

        $len = strlen($alt);
        if (($len === 12 || $len === 13) && str_starts_with($alt, '55')) {
            $alt = substr($alt, 2);
            $len = strlen($alt);
        }

        if (($len === 10 || $len === 11) && in_array((int) substr($alt, 0, 2), self::DDDS_VALIDOS, true)) {
            return '55'.$alt;
        }

        return null;
    }

    private function semNumero(string $raw): bool
    {
        return (bool) preg_match('/nao\s*(informado|confirmado|consta)/i', Str::ascii($raw));
    }

    private function digits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }

    /**
     * @return array{original:?string,normalized:?string,alt:?string,status:string,reason:?string}
     */
    private function result(?string $original, ?string $normalized, ?string $alt, string $status, ?string $reason): array
    {
        return [
            'original' => $original !== null ? trim($original) : null,
            'normalized' => $normalized,
            'alt' => $alt,
            'status' => $status,
            'reason' => $reason,
        ];
    }
}
