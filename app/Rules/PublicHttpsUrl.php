<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Garante que a URL da imagem seja pública e servida por HTTPS.
 *
 * O `image_url` do post social é uma referência a uma imagem que já existe na
 * internet; nada é baixado nem armazenado nesta etapa. Ainda assim, aceitar
 * http, `file://`, localhost ou IP privado transformaria o campo num vetor de
 * SSRF: quem chama a API escolheria o endereço que o servidor visitaria depois.
 * A regra fecha isso deixando passar apenas https para host público.
 */
class PublicHttpsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('A URL da imagem é inválida.');

            return;
        }

        $url = trim($value);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $fail('A URL da imagem é inválida.');

            return;
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if ($scheme !== 'https') {
            $fail('A URL da imagem deve usar HTTPS.');

            return;
        }

        if ($host === '') {
            $fail('A URL da imagem é inválida.');

            return;
        }

        if ($this->isLoopback($host) || $this->isPrivateAddress($host) || $this->isInternalHostname($host)) {
            $fail('A URL da imagem não pode apontar para endereço interno ou privado.');
        }
    }

    private function isLoopback(string $host): bool
    {
        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || $host === '::1'
            || $host === '[::1]';
    }

    private function isPrivateAddress(string $host): bool
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        }

        return false;
    }

    private function isInternalHostname(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        if (! str_contains($host, '.')) {
            return true;
        }

        foreach (['.local', '.internal', '.intranet', '.lan', '.localdomain', '.home', '.corp'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
