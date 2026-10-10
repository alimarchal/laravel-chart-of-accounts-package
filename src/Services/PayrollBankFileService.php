<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;

/**
 * The file that pays the net salaries through the bank: one row per employee with net pay, the bank account and the
 * narration, in a layout from accounting.payroll.bank_file.layouts (a list of columns). Employees without a bank account
 * are left out of the file and listed, so nobody is forgotten silently.
 */
class PayrollBankFileService
{
    private const HEADERS = [
        'employee_code' => 'Employee code', 'employee_name' => 'Employee name', 'national_id' => 'National ID', 'bank_name' => 'Bank',
        'bank_account' => 'Account / IBAN', 'amount' => 'Amount', 'narration' => 'Narration', 'month' => 'Month',
    ];

    /**
     * @return list<string>
     */
    public function layouts(): array
    {
        return array_keys((array) config('accounting.payroll.bank_file.layouts', []));
    }

    /**
     * @return array{rows: list<array<string, string>>, missing: list<array{code: string, name: string, net: string}>, total: string, count: int}
     */
    public function build(PayrollRun $run, string $layout = 'standard'): array
    {
        if (! in_array($run->status, ['posted', 'paid'], true)) {
            throw new AccountingException('Post the payroll first: the bank file is made from posted salaries.');
        }

        $columns = (array) (config("accounting.payroll.bank_file.layouts.{$layout}") ?? throw new AccountingException("There is no bank file layout called {$layout}."));
        $month = Carbon::parse($run->period_month);
        $narration = str_replace(':month', $month->format('F Y'), (string) config('accounting.payroll.bank_file.narration', 'Salary :month'));
        $employees = Employee::query()->whereIn('id', Payslip::query()->where('payroll_run_id', $run->id)->select('employee_id'))->get()->keyBy('id');
        $rows = [];
        $missing = [];
        $total = 0;

        foreach (Payslip::query()->where('payroll_run_id', $run->id)->orderBy('id')->get() as $slip) {
            $cents = Money::toCents($slip->net);
            $employee = $employees[$slip->employee_id] ?? null;

            if ($employee === null || $cents <= 0) {
                continue;
            }

            if (trim((string) $employee->bank_account) === '') {
                $missing[] = ['code' => $employee->code, 'name' => $employee->name, 'net' => $slip->net];

                continue;
            }

            $values = ['employee_code' => $employee->code, 'employee_name' => $employee->name, 'national_id' => (string) $employee->national_id, 'bank_name' => (string) $employee->bank_name, 'bank_account' => trim((string) $employee->bank_account), 'amount' => $slip->net, 'narration' => $narration, 'month' => $month->format('Y-m')];
            $rows[] = collect($columns)->mapWithKeys(fn (string $column): array => [self::HEADERS[$column] ?? $column => $values[$column] ?? ''])->all();
            $total += $cents;
        }

        return ['rows' => $rows, 'missing' => $missing, 'total' => Money::fromCents($total), 'count' => count($rows)];
    }

    /**
     * Employees with net pay but no bank account, for a warning on the run screen.
     *
     * @return list<array{code: string, name: string, net: string}>
     */
    public function missingAccounts(PayrollRun $run): array
    {
        $employees = Employee::query()->whereIn('id', Payslip::query()->where('payroll_run_id', $run->id)->select('employee_id'))->get()->keyBy('id');
        $missing = [];

        foreach (Payslip::query()->where('payroll_run_id', $run->id)->orderBy('id')->get() as $slip) {
            $employee = $employees[$slip->employee_id] ?? null;

            if ($employee !== null && Money::toCents($slip->net) > 0 && trim((string) $employee->bank_account) === '') {
                $missing[] = ['code' => $employee->code, 'name' => $employee->name, 'net' => $slip->net];
            }
        }

        return $missing;
    }
}
