<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;

/**
 * Exact money arithmetic in integer minor units (cents).
 *
 * Amounts are stored as DECIMAL(…, 2). Converting through strings instead of
 * floats keeps balance checks exact regardless of how many lines are summed.
 */
final class Money
{
    /** The largest whole amount that still fits integer cents (PHP_INT_MAX / 100). */
    private const MAX_WHOLE = 92233720368547758;

    /**
     * @throws AccountingException when the value is not a plain decimal number ("1,000.50", "1e3", "12abc") or is too large for integer cents
     */
    public static function toCents(int|float|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_int($amount)) {
            if (abs($amount) > self::MAX_WHOLE) {
                throw new AccountingException("The amount [{$amount}] is too large.");
            }

            return $amount * 100;
        }

        if (is_float($amount)) {
            if (! is_finite($amount) || abs($amount) >= self::MAX_WHOLE) {
                throw new AccountingException("The amount [{$amount}] is not a valid number.");
            }

            return (int) round($amount * 100);
        }

        $amount = trim($amount);

        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $amount, $matches) || ($matches[2] === '' && ($matches[3] ?? '') === '')) {
            throw new AccountingException("The amount [{$amount}] is not a valid number: use digits with an optional decimal point, without thousands separators.");
        }

        $negative = $matches[1] === '-';
        $digits = ltrim($matches[2], '0');

        $limit = (string) self::MAX_WHOLE;

        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new AccountingException("The amount [{$amount}] is too large.");
        }

        $whole = $digits === '' ? 0 : (int) $digits;
        $fraction = $matches[3] ?? '';
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
