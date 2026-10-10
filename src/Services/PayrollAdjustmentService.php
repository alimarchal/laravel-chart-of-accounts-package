<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollAdjustment;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryRevision;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Support\SalaryHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Variable pay: one-off earnings (bonus, extra allowance) and deductions (fine, recovery) of an employee in a month, recorded one at a
 * time or for many employees at once (a fixed amount, or a percent of each one's basic salary). The month's payroll run takes up the open ones;
 * a run that is recalculated, deleted or voided gives them back.
 */
class PayrollAdjustmentService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $data = Validator::make($input, [
            'employee_id' => ['required', 'integer', CompanyRule::exists('accounting_employees', 'id')],
            ...$this->commonRules(),
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
        ])->validate();

        return $this->resolve($data);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateBulk(array $input): array
    {
        $data = Validator::make($input, [
            ...$this->commonRules(),
            'method' => ['required', Rule::in(['fixed', 'percent_of_basic'])],
            'value' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', CompanyRule::exists('accounting_employees', 'id')],
        ])->validate();

        if ($data['method'] === 'percent_of_basic' && (float) $data['value'] > 1000) {
            throw ValidationException::withMessages(['value' => 'A percent above 1000 is probably a mistake.']);
        }

        return $this->resolve($data);
    }

    public function create(array $data): PayrollAdjustment
    {
        return DB::transaction(function () use ($data): PayrollAdjustment {
            $this->assertMonthOpen($data['month']);
            $row = PayrollAdjustment::query()->create($this->row($data, $data['employee_id'], (string) $data['amount']));
            AccountingAuditLog::record($row, 'PAYROLL_ADJUSTMENT_CREATED', null, null, ['employee_id' => $row->employee_id, 'month' => Carbon::parse($data['month'])->format('Y-m'), 'kind' => $row->kind, 'amount' => $row->amount]);

            return $row;
        });
    }

    /**
     * One adjustment for each active employee employed in the month (or for the ones named): a fixed amount, or a percent of the basic salary of the month.
     *
     * @return array{created: int, total: string, skipped: list<string>}
     */
    public function createBulk(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $month = Carbon::parse($data['month'])->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $employees = Employee::query()->where('is_active', true)->whereDate('join_date', '<=', $end->toDateString())
                ->where(fn ($query) => $query->whereNull('leave_date')->orWhereDate('leave_date', '>=', $month->toDateString()))
                ->when($data['employee_ids'] ?? [], fn ($query, $ids) => $query->whereIn('id', $ids))->orderBy('code')->get();
            $revisions = SalaryRevision::query()->orderBy('effective_from')->orderBy('id')->get()->groupBy('employee_id');
            $created = 0;
            $total = 0;
            $skipped = [];

            foreach ($employees as $employee) {
                $cents = $data['method'] === 'fixed'
                    ? Money::toCents((string) $data['value'])
                    : (int) round(SalaryHistory::salaryOn($employee, $end, $revisions[$employee->id] ?? collect()) * (float) $data['value'] / 100);

                if ($cents <= 0) {
                    $skipped[] = $employee->code;

                    continue;
                }

                try {
                    $this->assertMonthOpen($month->toDateString());
                } catch (AccountingException) {
                    $skipped[] = $employee->code;

                    continue;
                }

                PayrollAdjustment::query()->create($this->row($data, $employee->id, Money::fromCents($cents)));
                $created++;
                $total += $cents;
            }

            if ($created > 0) {
                AccountingAuditLog::record(PayrollAdjustment::query()->latest('id')->firstOrFail(), 'PAYROLL_ADJUSTMENT_BULK', null, null, ['month' => $month->format('Y-m'), 'count' => $created, 'total' => Money::fromCents($total), 'description' => $data['description']]);
            }

            return ['created' => $created, 'total' => Money::fromCents($total), 'skipped' => $skipped];
        });
    }

    /**
     * An adjustment a run has not taken up yet can be cancelled.
     */
    public function cancel(PayrollAdjustment $adjustment): PayrollAdjustment
    {
        if ($adjustment->status !== 'open') {
            throw new AccountingException($adjustment->status === 'included' ? 'A payroll run already includes this: delete or void the run first.' : 'This adjustment is already cancelled.');
        }

        $adjustment->forceFill(['status' => 'cancelled'])->save();
        AccountingAuditLog::record($adjustment, 'PAYROLL_ADJUSTMENT_CANCELLED', null, null, ['employee_id' => $adjustment->employee_id]);

        return $adjustment;
    }

    /**
     * The open adjustments of a month by employee, for the run to take up.
     *
     * @return array<int, list<PayrollAdjustment>>
     */
    public function openFor(Carbon $month): array
    {
        return PayrollAdjustment::query()->where('status', 'open')->whereDate('month', $month->copy()->startOfMonth()->toDateString())->orderBy('id')->get()->groupBy('employee_id')->map(fn ($rows): array => $rows->values()->all())->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function present(?string $month = null, ?string $status = null): array
    {
        $names = Employee::query()->pluck('name', 'id');
        $codes = Employee::query()->pluck('code', 'id');

        return PayrollAdjustment::query()
            ->when($month, fn ($query, $value) => $query->whereDate('month', Carbon::parse($value.'-01')->toDateString()))
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderByDesc('month')->orderBy('id')->limit(500)->get()
            ->map(fn (PayrollAdjustment $row): array => [
                'id' => $row->id, 'employee_id' => $row->employee_id, 'employee_code' => $codes[$row->employee_id] ?? '', 'employee_name' => $names[$row->employee_id] ?? '',
                'month' => $row->month->format('Y-m'), 'kind' => $row->kind, 'pay_component_id' => $row->pay_component_id, 'description' => $row->description, 'amount' => $row->amount,
                'taxable' => $row->taxable, 'status' => $row->status, 'payroll_run_id' => $row->payroll_run_id, 'notes' => $row->notes,
            ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function commonRules(): array
    {
        return [
            'month' => ['required', 'date'],
            'kind' => ['nullable', Rule::in(['earning', 'deduction'])],
            'pay_component_id' => ['nullable', 'integer', CompanyRule::exists('accounting_pay_components', 'id')],
            'account_id' => ['nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')],
            'description' => ['required', 'string', 'max:160'],
            'taxable' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * The kind and account come from the pay component when one is chosen; otherwise a deduction needs the account it is owed to, and an
     * earning goes to accounting.payroll.bonus_account (default: the salary expense account).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolve(array $data): array
    {
        $data['month'] = Carbon::parse($data['month'])->startOfMonth()->toDateString();

        if (! empty($data['pay_component_id'])) {
            $component = PayComponent::query()->findOrFail($data['pay_component_id']);
            $data['kind'] = $component->kind;
            $data['account_id'] = $component->account_id;
            $data['taxable'] = $data['taxable'] ?? $component->taxable;
        } else {
            $data['kind'] ??= 'earning';

            if ($data['kind'] === 'deduction' && empty($data['account_id'])) {
                throw ValidationException::withMessages(['account_id' => 'Choose a pay component or the account the deduction is owed to.']);
            }

            if ($data['kind'] === 'earning' && empty($data['account_id'])) {
                $code = (string) (config('accounting.payroll.bonus_account') ?: config('accounting.payroll.salary_expense_account'));
                $data['account_id'] = ChartOfAccount::query()->where('account_code', $code)->where('is_group', false)->value('id')
                    ?? throw ValidationException::withMessages(['account_id' => "The bonus account ({$code}) does not exist: choose an account or set accounting.payroll.bonus_account."]);
            }
        }

        if (! in_array($data['kind'], ['earning', 'deduction'], true)) {
            throw ValidationException::withMessages(['kind' => 'An adjustment pays or takes: earning or deduction.']);
        }

        $data['taxable'] = $data['kind'] === 'earning' && (bool) ($data['taxable'] ?? true);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function row(array $data, int $employeeId, string $amount): array
    {
        return ['employee_id' => $employeeId, 'month' => $data['month'], 'kind' => $data['kind'], 'pay_component_id' => $data['pay_component_id'] ?? null, 'account_id' => $data['account_id'], 'description' => $data['description'],
            'amount' => $amount, 'taxable' => $data['taxable'], 'status' => 'open', 'notes' => $data['notes'] ?? null];
    }

    /**
     * A month whose payroll is already posted is closed to new adjustments (a draft run is recalculated to take them up).
     */
    private function assertMonthOpen(string $month): void
    {
        $posted = DB::table('accounting_payroll_runs')->where('company_id', CurrentCompany::currentId())
            ->whereDate('period_month', Carbon::parse($month)->startOfMonth()->toDateString())->whereIn('status', ['posted', 'paid', 'approved', 'submitted'])->exists();

        if ($posted) {
            throw new AccountingException('The payroll of '.Carbon::parse($month)->format('F Y').' is already past the draft stage: add it to the next month, or void the run.');
        }
    }
}
