<?php

namespace App\Support;

/**
 * Remove credenciais antes de um texto de terceiro ir para log, banco ou API.
 *
 * A Evolution e o n8n devolvem o corpo da requisição quando recusam algo. Esse
 * corpo pode ecoar o `apikey` enviado, e copiá-lo para `last_error`, `forward_error`
 * ou `storage/logs` entregaria o token da clínica a quem lê o painel ou o log.
 * Aqui a remoção é feita na fronteira, para que todos esses destinos recebam só
 * o diagnóstico sem a credencial.
 */
final class RedatorDeSegredos
{
    private const MARCADOR = '***';

    public static function redigir(?string $texto, int $limite = 500): string
    {
        $texto = (string) $texto;

        if ($texto === '') {
            return '';
        }

        foreach ([
            config('services.evolution.admin_key'),
            config('services.evolution.webhook_secret'),
            config('services.n8n.webhook_token'),
        ] as $segredo) {
            if (is_string($segredo) && trim($segredo) !== '') {
                $texto = str_replace($segredo, self::MARCADOR, $texto);
            }
        }

        // Chaves JSON: "apikey": "abc", "token":"abc", "x-evolution-secret": ...
        $texto = preg_replace_callback(
            '/"(api-?key|api_?key|token|api_?token|secret|webhook_?secret|evolution_?token|authorization|x-evolution-secret|x-n8n-token)"\s*:\s*"[^"]*"/i',
            fn (array $correspondencia): string => substr(
                $correspondencia[0],
                0,
                (int) strpos($correspondencia[0], ':') + 1
            ).' "'.self::MARCADOR.'"',
            $texto
        ) ?? $texto;

        // Mesmos nomes em texto simples: apikey=abc, token: abc.
        $texto = preg_replace_callback(
            '/\b(api-?key|api_?key|token|api_?token|secret|webhook_?secret|evolution_?token|x-evolution-secret|x-n8n-token)\b\s*[:=]\s*[^\s,;"\}]+/i',
            fn (array $correspondencia): string => $correspondencia[1].'='.self::MARCADOR,
            $texto
        ) ?? $texto;

        return mb_strlen($texto) > $limite
            ? mb_substr($texto, 0, $limite).'…'
            : $texto;
    }
}
