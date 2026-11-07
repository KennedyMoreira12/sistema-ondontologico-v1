<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Cálculos financeiros compartilhados.
 *
 * A planilha CONTAS usa DAYS360, TODAY e SUM. Reproduzimos a MESMA conta de
 * dias (base 360) para que a conferência com o histórico não dependa de
 * arredondamento de dias corridos.
 *
 * Nenhuma taxa é fixada aqui: juros, multa e desconto só são calculados
 * quando quem chama informa o percentual. A cliente ainda não confirmou as
 * regras comerciais, e o sistema não deve inventá-las.
 */
final class FinanceHelper
{
    /**
     * Diferença em dias pela convenção 30/360 (a mesma do DAYS360 do Excel).
     *
     * Positivo quando `$fim` é depois de `$inicio`.
     */
    public static function dias360(Carbon|string $inicio, Carbon|string $fim): int
    {
        $de = Carbon::parse($inicio)->startOfDay();
        $ate = Carbon::parse($fim)->startOfDay();

        $diaDe = $de->day;
        $diaAte = $ate->day;

        if ($de->isLastOfMonth() && $de->month === 2) {
            $diaDe = 30;
        }

        if ($ate->isLastOfMonth() && $ate->month === 2 && $diaDe === 30) {
            $diaAte = 30;
        }

        if ($diaAte === 31 && $diaDe >= 30) {
            $diaAte = 30;
        }

        if ($diaDe === 31) {
            $diaDe = 30;
        }

        return (($ate->year - $de->year) * 360)
            + (($ate->month - $de->month) * 30)
            + ($diaAte - $diaDe);
    }

    /**
     * Dias de atraso de um vencimento até a data de referência.
     *
     * Conta vencida há menos de um dia 30/360 ainda não está atrasada.
     */
    public static function diasAtraso(Carbon|string $vencimento, Carbon|string|null $referencia = null): int
    {
        return max(0, self::dias360($vencimento, $referencia ?? today()));
    }

    /**
     * Juros simples proporcionais aos dias, na base informada (padrão 30).
     */
    public static function jurosSimples(
        float $valor,
        float $taxaPercentual,
        int $dias,
        int $baseDias = 30,
    ): float {
        if ($valor <= 0 || $taxaPercentual <= 0 || $dias <= 0 || $baseDias <= 0) {
            return 0.0;
        }

        return round($valor * ($taxaPercentual / 100) * ($dias / $baseDias), 2);
    }

    public static function multa(float $valor, float $percentual): float
    {
        if ($valor <= 0 || $percentual <= 0) {
            return 0.0;
        }

        return round($valor * ($percentual / 100), 2);
    }

    /**
     * @return array{juros: float, multa: float, total: float}
     */
    public static function encargos(
        float $valor,
        int $diasAtraso,
        float $jurosPercentual = 0,
        float $multaPercentual = 0,
        int $baseDias = 30,
    ): array {
        $juros = self::jurosSimples($valor, $jurosPercentual, $diasAtraso, $baseDias);
        $multa = self::multa($valor, $multaPercentual);

        return [
            'juros' => $juros,
            'multa' => $multa,
            'total' => round($valor + $juros + $multa, 2),
        ];
    }

    public static function formatarMoeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    /**
     * Totaliza valores por mês (chave `Y-m`).
     *
     * @param  iterable<array{data: mixed, valor: mixed}>  $registros
     * @return array<string, array{total: float, quantidade: int}>
     */
    public static function totalizarPorMes(iterable $registros): array
    {
        $meses = [];

        foreach ($registros as $registro) {
            $data = $registro['data'] ?? null;

            if ($data === null || $data === '') {
                continue;
            }

            $chave = Carbon::parse($data)->format('Y-m');
            $meses[$chave] ??= ['total' => 0.0, 'quantidade' => 0];
            $meses[$chave]['total'] += (float) ($registro['valor'] ?? 0);
            $meses[$chave]['quantidade']++;
        }

        ksort($meses);

        return $meses;
    }

    /**
     * Percentual seguro, sem divisão por zero.
     */
    public static function percentual(float $parte, float $total, int $casas = 2): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($parte / $total) * 100, $casas);
    }
}
