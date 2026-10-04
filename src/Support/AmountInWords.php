<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

/**
 * "1250.50" => "One Thousand Two Hundred Fifty and 50/100 Only" — the amount line of a printed voucher or cheque.
 * Uses the South Asian grouping (lakh, crore) when accounting.pdf.number_system is "south_asian".
 */
final class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function convert(string $amount, ?string $currency = null, ?string $system = null): string
    {
        $cents = abs(Money::toCents($amount));
        $whole = intdiv($cents, 100);
        $fraction = $cents % 100;
        $system ??= (string) config('accounting.pdf.number_system', 'international');

        $words = $whole === 0 ? 'Zero' : ($system === 'south_asian' ? self::southAsian($whole) : self::international($whole));

        return trim(($currency ? $currency.' ' : '').$words.($fraction > 0 ? sprintf(' and %02d/100', $fraction) : '').' Only');
    }

    private static function international(int $number): string
    {
        $parts = [];

        foreach ([1_000_000_000_000 => 'Trillion', 1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1000 => 'Thousand'] as $size => $name) {
            if ($number >= $size) {
                $parts[] = self::belowThousand(intdiv($number, $size)).' '.$name;
                $number %= $size;
            }
        }

        if ($number > 0) {
            $parts[] = self::belowThousand($number);
        }

        return implode(' ', $parts);
    }

    private static function southAsian(int $number): string
    {
        $parts = [];

        foreach ([10_000_000 => 'Crore', 100_000 => 'Lakh', 1000 => 'Thousand'] as $size => $name) {
            if ($number >= $size) {
                $count = intdiv($number, $size);
                $parts[] = ($size === 10_000_000 && $count >= 1000 ? self::southAsian($count) : self::belowThousand($count)).' '.$name;
                $number %= $size;
            }
        }

        if ($number > 0) {
            $parts[] = self::belowThousand($number);
        }

        return implode(' ', $parts);
    }

    private static function belowThousand(int $number): string
    {
        $words = [];

        if ($number >= 100) {
            $words[] = self::ONES[intdiv($number, 100)].' Hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $words[] = self::TENS[intdiv($number, 10)].($number % 10 ? ' '.self::ONES[$number % 10] : '');
        } elseif ($number > 0) {
            $words[] = self::ONES[$number];
        }

        return implode(' ', $words);
    }
}
