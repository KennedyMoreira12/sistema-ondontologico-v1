<?php

namespace App\Support;

/**
 * Regras clínicas do odontograma FDI.
 *
 * A notação canônica persistida é sempre a FDI; Universal e Palmer são
 * conversões de exibição. As superfícies aplicáveis dependem do quadrante
 * e da posição do dente — nunca de "esquerda/direita" genérica.
 */
class Odontograma
{
    public static function dentesFdi(string $denticao = 'permanente'): array
    {
        return config("odontograma.denticoes.{$denticao}.dentes", []);
    }

    public static function fdiValido(int $dente): bool
    {
        return static::quadrante($dente) !== null && static::posicao($dente) !== null;
    }

    public static function quadrante(int $dente): ?int
    {
        return $dente >= 10 && $dente < 90 ? intdiv($dente, 10) : null;
    }

    public static function posicao(int $dente): ?int
    {
        return $dente >= 10 && $dente < 90 ? $dente % 10 : null;
    }

    public static function denticao(int $dente): string
    {
        return in_array(static::quadrante($dente), [1, 2, 3, 4], true) ? 'permanente' : 'decidua';
    }

    public static function arcadaSuperior(int $dente): bool
    {
        return in_array(static::quadrante($dente), [1, 2, 5, 6], true);
    }

    /**
     * A face voltada para o palato na arcada superior vira "lingual" na
     * inferior: o mapeamento segue o quadrante, não um rótulo fixo.
     */
    public static function superficieFDI(int $dente): string
    {
        return static::arcadaSuperior($dente) ? 'palatina' : 'lingual';
    }

    public static function superficiesDoDente(int $dente): array
    {
        $posicao = static::posicao($dente);

        if ($posicao === null) {
            return [];
        }

        $superficies = ['vestibular', 'mesial', 'distal', static::superficieFDI($dente)];

        if ($posicao <= 3) {
            $superficies[] = 'incisal';
        } else {
            $superficies[] = 'oclusal';
        }

        return $superficies;
    }

    public static function nomeDoDente(int $dente): string
    {
        $posicao = static::posicao($dente);
        $quadrante = static::quadrante($dente);

        if ($posicao === null || $quadrante === null) {
            return "Dente {$dente}";
        }

        $decidua = static::denticao($dente) === 'decidua';
        $tipo = $decidua ? static::tipoDeciduo($posicao) : static::tipoPermanente($posicao);
        $arcada = static::arcadaSuperior($dente) ? 'superior' : 'inferior';
        $lado = in_array($quadrante, [1, 4, 5, 8], true) ? 'direito' : 'esquerdo';

        return "{$tipo} {$arcada} {$lado}";
    }

    public static function fdiParaUniversal(int $dente): string
    {
        $quadrante = static::quadrante($dente);
        $posicao = static::posicao($dente);

        if ($quadrante === null || $posicao === null) {
            return (string) $dente;
        }

        if (static::denticao($dente) === 'decidua') {
            $letras = range('A', 'T');

            return match ($quadrante) {
                5 => $letras[5 - $posicao],
                6 => $letras[4 + $posicao],
                7 => $letras[9 + $posicao],
                8 => $letras[14 + $posicao],
                default => (string) $dente,
            };
        }

        return match ($quadrante) {
            1 => (string) (9 - $posicao),
            2 => (string) (8 + $posicao),
            3 => (string) (25 - $posicao),
            4 => (string) (24 + $posicao),
            default => (string) $dente,
        };
    }

    public static function fdiParaPalmer(int $dente): string
    {
        $quadrante = static::quadrante($dente);
        $posicao = static::posicao($dente);

        if ($quadrante === null || $posicao === null) {
            return (string) $dente;
        }

        $colchete = match ($quadrante) {
            1, 5 => '┐',
            2, 6 => '┌',
            3, 7 => '┘',
            4, 8 => '└',
            default => '',
        };

        return "{$colchete}{$posicao}";
    }

    private static function tipoPermanente(int $posicao): string
    {
        return match ($posicao) {
            1 => 'Incisivo central',
            2 => 'Incisivo lateral',
            3 => 'Canino',
            4 => 'Primeiro pré-molar',
            5 => 'Segundo pré-molar',
            6 => 'Primeiro molar',
            7 => 'Segundo molar',
            8 => 'Terceiro molar',
            default => 'Dente',
        };
    }

    private static function tipoDeciduo(int $posicao): string
    {
        return match ($posicao) {
            1 => 'Incisivo central',
            2 => 'Incisivo lateral',
            3 => 'Canino',
            4 => 'Primeiro molar',
            5 => 'Segundo molar',
            default => 'Dente',
        };
    }
}
