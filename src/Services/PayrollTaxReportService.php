<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;

/**
 * The salary and the income tax withheld in a tax year, month by month, from the posted payslips: an employee's tax
 * certificate and the annual statement of everybody (what is filed with the tax authority). The tax year starts in
 * accounting.payroll.tax_year_start_month and is named by the year it starts in.
 */
class PayrollTaxReportService
{
    /**
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function taxYear(int $startYear): array
    {
        $start = max(1, min(12, (int) config('accounting.payroll.tax_year_start_month', 7)));
        $from = Carbon::create($startYear, $start, 1)->startOfDay();
        $to = $from->copy()->addYear()->subDay();

        return ['from' => $from, 'to' => $to, 'label' => $from->format('M Y').' – '.$to->format('M Y')];
    }

    /**
     * Taxable pay, tax withheld and net pay of employees by month: one row per payslip of the year.
     *
     * @return array<int, array<string, array{taxable: int, tax: int, gross: int, net: int}>> employee id => month (Y-m) => cents
     */
    private function figures(int $startYear, ?int $employeeId = null): array
    {
        ['from' => $from, 'to' => $to] = $this->taxYear($startYear);
        $runs = PayrollRun::query()->whereIn('status', ['posted', 'paid'])->whereDate('period_month', '>=', $from->toDateString())->whereDate('period_month', '<=', $to->toDateString())->pluck('period_month', 'id');
        $slips = Payslip::query()->whereIn('payroll_run_id', $runs->keys())->when($employeeId, fn ($query, $id) => $query->where('employee_id', $id))->get();
        $taxableComponents = PayComponent::query()->where('taxable', true)->pluck('id')->all();
        $taxable = [];

        foreach (PayslipLine::query()->whereIn('payslip_id', $slips->pluck('id'))->whereIn('kind', ['basic', 'earning', 'arrears'])->get(['payslip_id', 'pay_component_id', 'kind', 'amount']) as $line) {
            if ($line->kind !== 'earning' || $line->pay_component_id === null || in_array($line->pay_component_id, $taxableComponents, true)) {
                $taxable[$line->payslip_id] = ($taxable[$line->payslip_id] ?? 0) + Money::toCents($line->amount);
            }
        }

        $figures = [];

        foreach ($slips as $slip) {
            $month = Carbon::parse($runs[$slip->payroll_run_id])->format('Y-m');
            $row = $figures[$slip->employee_id][$month] ?? ['taxable' => 0, 'tax' => 0, 'gross' => 0, 'net' => 0];
            $figures[$slip->employee_id][$month] = ['taxable' => $row['taxable'] + ($taxable[$slip->id] ?? 0), 'tax' => $row['tax'] + Money::toCents($slip->tax), 'gross' => $row['gross'] + Money::toCents($slip->gross), 'net' => $row['net'] + Money::toCents($slip->net)];
        }

        return $figures;
    }

    /**
     * One employee's tax certificate for a tax year.
     *
     * @return array<string, mixed>
     */
    public function certificate(Employee $employee, int $startYear): array
    {
        $year = $this->taxYear($startYear);
        $months = [];
        $totals = ['taxable' => 0, 'tax' => 0, 'gross' => 0, 'net' => 0];

        foreach ($this->figures($startYear, $employee->id)[$employee->id] ?? [] as $month => $row) {
            $months[] = ['month' => $month, 'taxable' => Money::fromCents($row['taxable']), 'tax' => Money::fromCents($row['tax']), 'gross' => Money::fromCents($row['gross']), 'net' => Money::fromCents($row['net'])];

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $row[$key];
            }
        }

        usort($months, fn (array $a, array $b): int => strcmp($a['month'], $b['month']));

        return [
            'tax_year' => $startYear, 'label' => $year['label'], 'from' => $year['from']->toDateString(), 'to' => $year['to']->toDateString(),
            'employee' => ['id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'national_id' => $employee->national_id, 'designation' => $employee->designation, 'join_date' => $employee->join_date->toDateString()],
            'months' => $months, 'totals' => array_map(fn (int $cents): string => Money::fromCents($cents), $totals),
        ];
    }

    /**
     * Everybody's taxable pay and tax withheld for a tax year.
     *
     * @return list<array<string, string>>
     */
    public function annual(int $startYear): array
    {
        $figures = $this->figures($startYear);
        $employees = Employee::query()->whereIn('id', array_keys($figures))->orderBy('code')->get()->keyBy('id');
        $rows = [];

        foreach ($employees as $id => $employee) {
            $sum = ['taxable' => 0, 'tax' => 0, 'gross' => 0, 'net' => 0];

            foreach ($figures[$id] as $row) {
                foreach ($sum as $key => $value) {
                    $sum[$key] = $value + $row[$key];
                }
            }

            $rows[] = [
                'Employee code' => $employee->code, 'Employee name' => $employee->name, 'National ID' => (string) $employee->national_id, 'Months paid' => (string) count($figures[$id]),
                'Taxable income' => Money::fromCents($sum['taxable']), 'Tax withheld' => Money::fromCents($sum['tax']), 'Gross pay' => Money::fromCents($sum['gross']), 'Net pay' => Money::fromCents($sum['net']),
            ];
        }

        return $rows;
    }
}
