<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * How many days a salary month counts, for proration (joiners, leavers, unpaid absence), leave encashment and gratuity.
 * accounting.payroll.day_basis: calendar (the real 28–31 days), fixed (accounting.payroll.gratuity.days_per_month, e.g. 30 every
 * month) or working (the month's days less the weekly off days, accounting.payroll.weekly_off_days).
 */
final class PayrollDays
{
    public static function basis(): string
    {
        $basis = (string) config('accounting.payroll.day_basis', 'calendar');

        return in_array($basis, ['calendar', 'fixed', 'working'], true) ? $basis : 'calendar';
    }

    /** The days the month counts under the basis. */
    public static function inMonth(CarbonInterface $month): int
    {
        $start = Carbon::parse($month)->startOfMonth();

        return match (self::basis()) {
            'fixed' => max(1, (int) round((float) config('accounting.payroll.gratuity.days_per_month', 30))),
            'working' => max(1, self::between($start, $start->copy()->endOfMonth())),
            default => $start->daysInMonth,
        };
    }

    /** The days from one date to another (both included) under the basis, never more than the month counts. */
    public static function between(CarbonInterface $from, CarbonInterface $to): int
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        if ($to->lt($from)) {
            return 0;
        }

        if (self::basis() === 'working') {
            $off = array_map('intval', (array) config('accounting.payroll.weekly_off_days', [0]));
            $count = 0;

            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                if (! in_array($day->dayOfWeek, $off, true)) {
                    $count++;
                }
            }

            return $count;
        }

        $days = (int) round($from->diffInDays($to)) + 1;

        return self::basis() === 'fixed' ? min($days, self::inMonth($from)) : $days;
    }
}
