<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

/**
 * Converts journal lines to base-currency amounts (amount × fx_rate_to_base, rounded to cents) and
 * keeps the entry balanced in base currency.
 *
 * Rounding each line can leave the base totals a few cents apart. That residual is absorbed by the
 * line with the largest amount (first one on ties). The rule is symmetric: a reversal (same lines,
 * debits and credits swapped) gets exactly the mirrored base amounts, so the pair nets to zero.
 */
final class BaseAmounts
{
    /**
     * @param  array<int|string, array{debit: int|float|string|null, credit: int|float|string|null}>  $lines  keyed by line id
     * @return array<int|string, array{base_debit: string, base_credit: string}>
     */
    public static function compute(array $lines, int|float|string $fxRate): array
    {
        $rate = (string) $fxRate;
        $result = [];
        $residual = 0;
        $largestKey = null;
        $largestCents = -1;

        foreach ($lines as $key => $line) {
            $debit = Money::toCents($line['debit'] ?? 0);
            $credit = Money::toCents($line['credit'] ?? 0);
            $baseDebit = self::convert($debit, $rate);
            $baseCredit = self::convert($credit, $rate);

            $result[$key] = ['debit' => $baseDebit, 'credit' => $baseCredit, 'is_debit' => $debit > 0];
            $residual += $baseDebit - $baseCredit;

            $amount = max($debit, $credit);

            if ($amount > $largestCents) {
                $largestCents = $amount;
                $largestKey = $key;
            }
        }

        if ($residual !== 0 && $largestKey !== null) {
            if ($result[$largestKey]['is_debit']) {
                $result[$largestKey]['debit'] -= $residual;
            } else {
                $result[$largestKey]['credit'] += $residual;
            }
        }

        return array_map(fn (array $line) => [
            'base_debit' => Money::fromCents($line['debit']),
            'base_credit' => Money::fromCents($line['credit']),
        ], $result);
    }

    /**
     * cents × rate, rounded half away from zero, without float error on the rate's decimals.
     */
    public static function convert(int $cents, string $rate): int
    {
        if ($cents === 0) {
            return 0;
        }

        if (function_exists('bcmul')) {
            $product = bcmul((string) $cents, $rate, 3);

            return (int) round((float) $product);
        }

        return (int) round($cents * (float) $rate);
    }
}
