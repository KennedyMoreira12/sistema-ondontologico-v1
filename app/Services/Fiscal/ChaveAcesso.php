<?php

namespace App\Services\Fiscal;

use DateTimeInterface;

/**
 * Geração da chave de acesso do documento fiscal.
 *
 * NF-e/NFC-e usam o layout nacional de 44 dígitos com dígito verificador
 * módulo 11. NFS-e não tem chave nacional única — cada município define a
 * sua — então aqui é gerada uma chave de 44 dígitos no padrão do emitente
 * (município + CNPJ + série + número + sufixo), sem DV.
 *
 * A chave só é usada como identidade local quando o provedor não devolve a
 * dele. Provedor real sempre tem precedência.
 */
final class ChaveAcesso
{
    /**
     * @param  string  $codigoMunicipio  código IBGE de 7 dígitos
     */
    public static function gerar(
        string $tipo,
        string $cnpj,
        string $serie,
        string $numero,
        DateTimeInterface $data,
        string $codigoMunicipio = '',
    ): string {
        $cnpj = self::somenteDigitos($cnpj);

        if (strlen($cnpj) !== 14) {
            throw new FiscalException('CNPJ do emitente inválido para gerar a chave de acesso.');
        }

        return match ($tipo) {
            'nfe', 'nfce' => self::chaveNfe($cnpj, $serie, $numero, $data, $tipo === 'nfce' ? '65' : '55'),
            default => self::chaveNfse($cnpj, $serie, $numero, $data, $codigoMunicipio),
        };
    }

    private static function chaveNfe(string $cnpj, string $serie, string $numero, DateTimeInterface $data, string $modelo): string
    {
        // A UF não é conhecida aqui; usamos 99 (ambiente nacional) como
        // marcador. Provedor real sobrescreve a chave com a devolvida por ele.
        $base = '99'
            .$data->format('ym')
            .$cnpj
            .$modelo
            .str_pad(self::somenteDigitos($serie), 3, '0', STR_PAD_LEFT)
            .str_pad(self::somenteDigitos($numero), 9, '0', STR_PAD_LEFT)
            .'1'
            .str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        return $base.self::digitoVerificador($base);
    }

    private static function chaveNfse(string $cnpj, string $serie, string $numero, DateTimeInterface $data, string $codigoMunicipio): string
    {
        $municipio = str_pad(self::somenteDigitos($codigoMunicipio), 7, '0', STR_PAD_LEFT);

        $base = $municipio
            .$cnpj
            .str_pad(self::somenteDigitos($serie), 3, '0', STR_PAD_LEFT)
            .str_pad(self::somenteDigitos($numero), 9, '0', STR_PAD_LEFT)
            .$data->format('ymd')
            .str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        return substr($base, 0, 44);
    }

    /**
     * Dígito verificador módulo 11, pesos 2..9 da direita para a esquerda.
     */
    public static function digitoVerificador(string $base): string
    {
        $soma = 0;
        $peso = 2;

        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $soma += (int) $base[$i] * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }

        $resto = $soma % 11;
        $digito = 11 - $resto;

        return (string) ($digito >= 10 ? 0 : $digito);
    }

    public static function valida(string $chave): bool
    {
        if (! preg_match('/^\d{44}$/', $chave)) {
            return false;
        }

        $base = substr($chave, 0, 43);

        return self::digitoVerificador($base) === $chave[43];
    }

    public static function somenteDigitos(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }
}
