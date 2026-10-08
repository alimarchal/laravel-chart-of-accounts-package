<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryRevision;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

/**
 * What an employee earns as basic pay on a day, and for a month, given the history of salary revisions. A revision that
 * takes effect in the middle of a month pays the old salary up to the day before and the new one from that day.
 */
class SalaryHistory
{
    /**
     * Monthly salary in cents on a day.
     *
     * @param  Collection<int, SalaryRevision>  $revisions  of this employee, oldest first
     */
    public static function salaryOn(Employee $employee, CarbonInterface $day, Collection $revisions): int
    {
        if ($revisions->isEmpty()) {
            return Money::toCents($employee->base_salary);
        }

        $date = $day->toDateString();
        $current = $revisions->last(fn (SalaryRevision $revision): bool => $revision->effective_from->toDateString() <= $date);

        return Money::toCents($current ? $current->new_salary : $revisions->first()->old_salary);
    }

    /**
     * Basic pay of a month in cents and the days the employee was employed in it.
     *
     * @param  Collection<int, SalaryRevision>  $revisions  of this employee, oldest first
     * @return array{cents: int, worked: int, days: int}
     */
    public static function basicForMonth(Employee $employee, CarbonInterface $period, Collection $revisions): array
    {
        $start = Carbon::parse($period)->startOfMonth();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $days = $start->daysInMonth;
        $from = Carbon::parse($employee->join_date)->startOfDay()->max($start);
        $to = ($employee->leave_date ? Carbon::parse($employee->leave_date)->startOfDay()->min($end) : $end)->copy()->startOfDay();
        $worked = (int) round($from->diffInDays($to)) + 1;

        $changesInside = $revisions->contains(fn (SalaryRevision $revision): bool => $revision->effective_from->gt($from) && $revision->effective_from->lte($to));

        if (! $changesInside) {
            return ['cents' => (int) round(self::salaryOn($employee, $from, $revisions) * $worked / $days), 'worked' => $worked, 'days' => $days];
        }

        $total = 0;

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $total += self::salaryOn($employee, $day, $revisions);
        }

        return ['cents' => (int) round($total / $days), 'worked' => $worked, 'days' => $days];
    }
}
