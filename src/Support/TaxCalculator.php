<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

/**
 * Tax arithmetic in integer cents. The rate is a percentage (17 = 17%, up to four decimals) and tax is rounded
 * half away from zero to the cent. For a tax-inclusive amount the tax is gross × rate / (100 + rate), and the base is
 * what remains, so base + tax always equals the gross exactly.
 */
final class TaxCalculator
{
    /**
     * @return array{base: int, tax: int, gross: int} cents
     */
    public static function split(int $cents, string $rate, bool $inclusive): array
    {
        $rate = self::normalise($rate);

        if ($cents === 0 || $rate === '0') {
            return ['base' => $cents, 'tax' => 0, 'gross' => $cents];
        }

        if ($inclusive) {
            $tax = self::divide(self::multiply($cents, $rate), self::add('100', $rate));
            $tax = self::roundHalfAway($tax);

            return ['base' => $cents - $tax, 'tax' => $tax, 'gross' => $cents];
        }

        $tax = self::roundHalfAway(self::divide(self::multiply($cents, $rate), '100'));

        return ['base' => $cents, 'tax' => $tax, 'gross' => $cents + $tax];
    }

    private static function normalise(string $rate): string
    {
        $rate = rtrim(rtrim(number_format((float) $rate, 4, '.', ''), '0'), '.');

        return $rate === '' ? '0' : $rate;
    }

    private static function multiply(int $cents, string $rate): string
    {
        return function_exists('bcmul') ? bcmul((string) $cents, $rate, 6) : (string) ($cents * (float) $rate);
    }

    private static function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, 6) : (string) ((float) $a + (float) $b);
    }

    private static function divide(string $a, string $b): string
    {
        return function_exists('bcdiv') ? bcdiv($a, $b, 6) : (string) ((float) $a / (float) $b);
    }

    private static function roundHalfAway(string $value): int
    {
        return (int) round((float) $value, 0, PHP_ROUND_HALF_UP);
    }
}
