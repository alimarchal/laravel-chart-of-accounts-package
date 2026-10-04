<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

/**
 * Exact money arithmetic in integer minor units (cents).
 *
 * Amounts are stored as DECIMAL(…, 2). Converting through strings instead of
 * floats keeps balance checks exact regardless of how many lines are summed.
 */
final class Money
{
    public static function toCents(int|float|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_int($amount)) {
            return $amount * 100;
        }

        if (is_float($amount)) {
            return (int) round($amount * 100);
        }

        $amount = trim($amount);
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '+-');

        if (! preg_match('/^(\d*)(?:\.(\d*))?$/', $amount, $matches)) {
            return (int) round(((float) $amount) * 100) * ($negative ? -1 : 1);
        }

        $whole = (int) ($matches[1] === '' ? '0' : $matches[1]);
        $fraction = $matches[2] ?? '';
        $cents = (int) str_pad(substr($fraction, 0, 2), 2, '0');

        // Round half up on the third decimal, if any.
        if (strlen($fraction) > 2 && (int) $fraction[2] >= 5) {
            $cents++;
        }

        $total = $whole * 100 + $cents;

        return $negative ? -$total : $total;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function equals(int|float|string|null $a, int|float|string|null $b): bool
    {
        return self::toCents($a) === self::toCents($b);
    }
}
