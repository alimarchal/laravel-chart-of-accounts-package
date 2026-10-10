<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Management figures from posted payroll: what each month cost and how it moved, what each cost center cost, and how
 * many people were paid, joined and left.
 */
class PayrollReportService
{
    /**
     * Month by month for a year: headcount, gross, deductions, tax, net, employer cost, total cost and the change on the month before.
     *
     * @return list<array<string, mixed>>
     */
    public function comparison(int $year): array
    {
        $runs = PayrollRun::query()->whereIn('status', ['posted', 'paid'])->whereYear('period_month', $year)->orderBy('period_month')->get();
        $counts = Payslip::query()->whereIn('payroll_run_id', $runs->pluck('id'))->selectRaw('payroll_run_id, count(*) as people')->groupBy('payroll_run_id')->pluck('people', 'payroll_run_id');
        $rows = [];
        $previous = null;

        foreach ($runs as $run) {
            $cost = Money::toCents($run->gross) + Money::toCents($run->employer);
            $people = (int) ($counts[$run->id] ?? 0);
            $rows[] = [
                'month' => Carbon::parse($run->period_month)->format('Y-m'), 'status' => $run->status, 'employees' => $people, 'gross' => $run->gross, 'deductions' => $run->deductions, 'tax' => $run->tax, 'net' => $run->net,
                'employer' => $run->employer, 'total_cost' => Money::fromCents($cost), 'cost_per_employee' => $people > 0 ? Money::fromCents((int) round($cost / $people)) : '0.00',
                'change' => $previous === null ? null : Money::fromCents($cost - $previous), 'change_percent' => $previous ? round(($cost - $previous) / $previous * 100, 1) : null,
            ];
            $previous = $cost;
        }

        return $rows;
    }

    /**
     * What each cost center cost between two months (gross pay and arrears plus the employer's contributions), with the people it paid.
     *
     * @return list<array<string, mixed>>
     */
    public function costCenters(string $fromMonth, string $toMonth): array
    {
        $from = Carbon::parse($fromMonth)->startOfMonth();
        $to = Carbon::parse($toMonth)->startOfMonth();
        $runs = PayrollRun::query()->whereIn('status', ['posted', 'paid'])->whereDate('period_month', '>=', $from->toDateString())->whereDate('period_month', '<=', $to->toDateString())->pluck('id');
        $slips = Payslip::query()->whereIn('payroll_run_id', $runs)->get(['id', 'employee_id']);
        $center = Employee::query()->whereIn('id', $slips->pluck('employee_id'))->pluck('cost_center_id', 'id');
        $slipEmployee = $slips->pluck('employee_id', 'id');
        $totals = [];
        $people = [];

        foreach ($slips as $slip) {
            $people[(int) ($center[$slip->employee_id] ?? 0)][$slip->employee_id] = true;
        }

        foreach (PayslipLine::query()->whereIn('payslip_id', $slips->pluck('id'))->whereIn('kind', ['basic', 'earning', 'arrears', 'employer'])->get(['payslip_id', 'kind', 'amount']) as $line) {
            $key = (int) ($center[$slipEmployee[$line->payslip_id]] ?? 0);
            $totals[$key]['pay'] = ($totals[$key]['pay'] ?? 0) + ($line->kind === 'employer' ? 0 : Money::toCents($line->amount));
            $totals[$key]['employer'] = ($totals[$key]['employer'] ?? 0) + ($line->kind === 'employer' ? Money::toCents($line->amount) : 0);
        }

        $names = CostCenter::query()->get(['id', 'code', 'name'])->keyBy('id');
        $rows = [];

        foreach ($totals as $id => $sum) {
            $rows[] = [
                'cost_center' => $id ? ($names[$id]->code.' '.$names[$id]->name) : 'No cost center', 'employees' => count($people[$id] ?? []), 'pay' => Money::fromCents($sum['pay']),
                'employer' => Money::fromCents($sum['employer']), 'total_cost' => Money::fromCents($sum['pay'] + $sum['employer']),
            ];
        }

        usort($rows, fn (array $a, array $b): int => Money::toCents($b['total_cost']) <=> Money::toCents($a['total_cost']));

        return $rows;
    }

    /**
     * Joiners, leavers and people on the payroll at the end of each month of a year.
     *
     * @return list<array<string, mixed>>
     */
    public function headcount(int $year): array
    {
        $employees = Employee::query()->get(['join_date', 'leave_date', 'is_active']);
        $rows = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $rows[] = [
                'month' => $start->format('Y-m'),
                'joined' => $employees->filter(fn (Employee $employee): bool => $employee->join_date->between($start, $end))->count(),
                'left' => $employees->filter(fn (Employee $employee): bool => $employee->leave_date !== null && $employee->leave_date->between($start, $end))->count(),
                'on_payroll' => $employees->filter(fn (Employee $employee): bool => $employee->join_date->lte($end) && ($employee->leave_date === null || $employee->leave_date->gte($end->copy()->startOfDay())))->count(),
            ];
        }

        return $rows;
    }
}
