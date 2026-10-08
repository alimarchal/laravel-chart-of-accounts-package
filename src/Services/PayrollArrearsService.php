<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\EmployeeComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollArrear;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGradeComponent;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryRevision;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Support\SalaryHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Back pay: when a raise is applied late, the difference between what was paid and what should have been paid for the
 * months in between is worked out from the payslips already posted, shown for review, approved, and paid with a payroll run
 * (as its own payslip line, taxed as if it had been paid in the months it belongs to).
 *
 * Only months with a posted or paid payslip count, months already claimed by other arrears are skipped, and a month in which
 * the employee was overpaid is ignored (it is not recovered). Allowances that are a percent of the basic follow the difference.
 */
class PayrollArrearsService
{
    public function __construct(private readonly PayrollStructureService $structure) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $data = Validator::make($input, [
            'from_month' => ['required', 'date'],
            'payment_month' => ['required', 'date'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
            'salary_grade_id' => ['nullable', 'integer', CompanyRule::exists('accounting_salary_grades', 'id')],
            'notes' => ['nullable', 'string', 'max:200'],
        ])->validate();

        if (Carbon::parse($data['payment_month'])->startOfMonth()->lte(Carbon::parse($data['from_month'])->startOfMonth())) {
            throw ValidationException::withMessages(['payment_month' => 'The month the arrears are paid in must be after the first month they cover.']);
        }

        return $data;
    }

    /**
     * Work the arrears out (nothing is saved): one row per employee owed something, with the months behind it.
     *
     * @param  array<string, mixed>  $data  validated
     * @return list<array<string, mixed>>
     */
    public function calculate(array $data): array
    {
        $from = Carbon::parse($data['from_month'])->startOfMonth();
        $last = Carbon::parse($data['payment_month'])->startOfMonth()->subMonth();
        $employees = $this->structure->employeesFor($data);

        if ($employees->isEmpty()) {
            return [];
        }

        $employeeIds = $employees->pluck('id');
        $revisions = SalaryRevision::query()->whereIn('employee_id', $employeeIds)->orderBy('effective_from')->orderBy('id')->get()->groupBy('employee_id');
        $runs = PayrollRun::query()->whereIn('status', ['posted', 'paid'])->whereDate('period_month', '>=', $from->toDateString())->whereDate('period_month', '<=', $last->toDateString())->pluck('period_month', 'id');
        $slips = Payslip::query()->whereIn('payroll_run_id', $runs->keys())->whereIn('employee_id', $employeeIds)->get(['id', 'payroll_run_id', 'employee_id', 'basic']);
        $taxableComponents = PayComponent::query()->where('taxable', true)->pluck('id')->all();
        $taxable = [];

        foreach (PayslipLine::query()->whereIn('payslip_id', $slips->pluck('id'))->whereIn('kind', ['basic', 'earning'])->get(['payslip_id', 'pay_component_id', 'kind', 'amount']) as $line) {
            if ($line->kind === 'basic' || in_array($line->pay_component_id, $taxableComponents, true)) {
                $taxable[$line->payslip_id] = ($taxable[$line->payslip_id] ?? 0) + Money::toCents($line->amount);
            }
        }

        $claimed = [];

        foreach (PayrollArrear::query()->whereIn('employee_id', $employeeIds)->where('status', '<>', 'cancelled')->get() as $arrear) {
            foreach ($arrear->months() as $month) {
                $claimed[$arrear->employee_id.'|'.$month['month']] = true;
            }
        }

        $percent = $this->percentOfBasic($employees);
        $rows = [];

        foreach ($employees as $employee) {
            $months = [];
            $total = 0;

            foreach ($slips->where('employee_id', $employee->id) as $slip) {
                $month = Carbon::parse($runs[$slip->payroll_run_id])->startOfMonth();
                $key = $month->format('Y-m');

                if (isset($claimed[$employee->id.'|'.$key])) {
                    continue;
                }

                $due = SalaryHistory::basicForMonth($employee, $month, $revisions[$employee->id] ?? collect())['cents'];
                $paid = Money::toCents($slip->basic);
                $difference = (int) round(($due - $paid) * (1 + ($percent[$employee->id] ?? 0) / 100));

                if ($due <= $paid || $difference <= 0) {
                    continue;
                }

                $months[] = ['month' => $key, 'paid' => $paid, 'due' => $due, 'difference' => $difference, 'taxable' => $taxable[$slip->id] ?? $paid];
                $total += $difference;
            }

            if ($total > 0) {
                usort($months, fn (array $a, array $b): int => strcmp($a['month'], $b['month']));
                $rows[] = [
                    'employee_id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'current_salary' => $employee->base_salary,
                    'from_month' => $months[0]['month'], 'to_month' => end($months)['month'], 'months' => $months, 'amount' => Money::fromCents($total),
                ];
            }
        }

        return $rows;
    }

    /**
     * Save the arrears worked out as drafts, one per employee.
     *
     * @param  array<string, mixed>  $data  validated
     * @return list<PayrollArrear>
     */
    public function create(array $data): array
    {
        $rows = $this->calculate($data);

        return DB::transaction(function () use ($rows, $data): array {
            $created = [];

            foreach ($rows as $row) {
                $arrear = PayrollArrear::query()->create([
                    'employee_id' => $row['employee_id'], 'from_month' => $row['from_month'].'-01', 'to_month' => $row['to_month'].'-01',
                    'payment_month' => Carbon::parse($data['payment_month'])->startOfMonth()->toDateString(), 'amount' => $row['amount'],
                    'breakdown' => json_encode($row['months']), 'status' => 'draft', 'notes' => $data['notes'] ?? null,
                ]);
                AccountingAuditLog::record($arrear, 'PAYROLL_ARREARS_CREATED', null, null, ['employee' => $row['code'], 'amount' => $row['amount'], 'from' => $row['from_month'], 'to' => $row['to_month']]);
                $created[] = $arrear;
            }

            return $created;
        });
    }

    public function approve(PayrollArrear $arrear): PayrollArrear
    {
        if ($arrear->status !== 'draft') {
            throw new AccountingException('Only arrears waiting for approval can be approved.');
        }

        $arrear->forceFill(['status' => 'approved', 'approved_by' => Auth::id()])->save();
        AccountingAuditLog::record($arrear, 'PAYROLL_ARREARS_APPROVED', null, null, ['amount' => $arrear->amount]);

        return $arrear->refresh();
    }

    /**
     * Approve every draft (optionally of one payment month).
     *
     * @return int how many were approved
     */
    public function approveAll(?string $paymentMonth = null): int
    {
        $count = 0;

        foreach (PayrollArrear::query()->where('status', 'draft')->when($paymentMonth, fn ($query, $month) => $query->whereDate('payment_month', Carbon::parse($month)->startOfMonth()->toDateString()))->get() as $arrear) {
            $this->approve($arrear);
            $count++;
        }

        return $count;
    }

    public function cancel(PayrollArrear $arrear): PayrollArrear
    {
        if ($arrear->status === 'included') {
            throw new AccountingException('These arrears are part of a payroll run: delete or void the run first.');
        }

        if ($arrear->status === 'cancelled') {
            return $arrear;
        }

        $arrear->forceFill(['status' => 'cancelled'])->save();
        AccountingAuditLog::record($arrear, 'PAYROLL_ARREARS_CANCELLED', null, null, ['amount' => $arrear->amount]);

        return $arrear->refresh();
    }

    /**
     * The arrears register: every row with its employee, and totals by status and by month of payment.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function report(?string $status = null, ?string $paymentMonth = null): array
    {
        $query = PayrollArrear::query()->orderByDesc('payment_month')->orderBy('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($paymentMonth) {
            $query->whereDate('payment_month', Carbon::parse($paymentMonth)->startOfMonth()->toDateString());
        }

        $arrears = $query->get();
        $employees = Employee::query()->whereIn('id', $arrears->pluck('employee_id'))->get(['id', 'code', 'name'])->keyBy('id');
        $rows = $arrears->map(fn (PayrollArrear $arrear): array => $this->present($arrear, $employees[$arrear->employee_id] ?? null))->values()->all();
        $live = $arrears->where('status', '<>', 'cancelled');

        return ['rows' => $rows, 'totals' => [
            'count' => $live->count(),
            'amount' => Money::fromCents($live->sum(fn (PayrollArrear $arrear): int => Money::toCents($arrear->amount))),
            'by_status' => $arrears->groupBy('status')->map(fn ($group): string => Money::fromCents($group->sum(fn (PayrollArrear $arrear): int => Money::toCents($arrear->amount))))->all(),
            'by_payment_month' => $live->groupBy(fn (PayrollArrear $arrear): string => $arrear->payment_month->format('Y-m'))->map(fn ($group): string => Money::fromCents($group->sum(fn (PayrollArrear $arrear): int => Money::toCents($arrear->amount))))->all(),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PayrollArrear $arrear, ?Employee $employee = null): array
    {
        $employee ??= Employee::query()->find($arrear->employee_id);

        return [
            'id' => $arrear->id, 'employee_id' => $arrear->employee_id, 'employee_code' => $employee?->code, 'employee_name' => $employee?->name,
            'from_month' => $arrear->from_month->format('Y-m'), 'to_month' => $arrear->to_month->format('Y-m'), 'payment_month' => $arrear->payment_month->format('Y-m'),
            'amount' => $arrear->amount, 'status' => $arrear->status, 'payroll_run_id' => $arrear->payroll_run_id, 'notes' => $arrear->notes,
            'months' => array_map(fn (array $month): array => ['month' => $month['month'], 'paid' => Money::fromCents($month['paid']), 'due' => Money::fromCents($month['due']), 'difference' => Money::fromCents($month['difference'])], $arrear->months()),
        ];
    }

    /**
     * Sum of the percent-of-basic earnings each employee carries (so back pay on the basic brings the allowances along).
     *
     * @param  iterable<int, Employee>  $employees
     * @return array<int, float>
     */
    private function percentOfBasic(iterable $employees): array
    {
        $components = PayComponent::query()->where('kind', 'earning')->where('method', 'percent_of_basic')->where('is_active', true)->get()->keyBy('id');

        if ($components->isEmpty()) {
            return [];
        }

        $assigned = EmployeeComponent::query()->whereIn('pay_component_id', $components->keys())->get()->groupBy('employee_id');
        $gradeLinks = SalaryGradeComponent::query()->whereIn('pay_component_id', $components->keys())->get()->groupBy('salary_grade_id');
        $result = [];

        foreach ($employees as $employee) {
            $links = [];

            foreach ($assigned[$employee->id] ?? [] as $link) {
                $links[$link->pay_component_id] = $link->value;
            }

            foreach ($employee->salary_grade_id ? ($gradeLinks[$employee->salary_grade_id] ?? []) : [] as $link) {
                $links += [$link->pay_component_id => $link->value];
            }

            $result[$employee->id] = array_sum(array_map(fn ($id) => (float) ($links[$id] ?? $components[$id]->value), array_values(array_filter(array_keys($links), fn ($id) => $components->has($id)))));
        }

        return $result;
    }
}
