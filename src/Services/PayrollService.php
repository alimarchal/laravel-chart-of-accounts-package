<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\EmployeeComponent;
use Alimarchal\LaravelChartOfAccounts\Models\EmployeeScheme;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollArrear;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGrade;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGradeComponent;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryRevision;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Support\SalaryHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Salaries: employees and the allowances and deductions they carry, a monthly payroll run that works out every
 * payslip, and the books.
 *
 * A run is a draft until it is posted: posting books one journal entry (basic pay and earnings as expense, deductions
 * and income tax withheld as liabilities, the net pay as a liability to the employees) in the payroll module; paying
 * clears the net liability against a bank or cash account. A run can be recalculated while it is a draft and voided
 * (its entries reversed) afterwards. A new joiner or leaver is paid for the days employed in the month.
 */
class PayrollService
{
    public const ORIGIN = 'payroll';

    public function __construct(private readonly JournalEntryService $journals) {}

    // -- employees and components ------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateComponent(array $input, ?PayComponent $component = null): array
    {
        $kind = $input['kind'] ?? null;

        $data = Validator::make($input, [
            'code' => ['required', 'string', 'max:30', CompanyRule::unique('accounting_pay_components', 'code')->ignore($component?->id)],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(PayComponent::KINDS)],
            'method' => ['required', Rule::in(array_keys(PayComponent::METHODS))],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'rate' => [($input['method'] ?? null) === 'quantity_rate' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'unit' => ['nullable', 'string', 'max:20'],
            'taxable' => ['nullable', 'boolean'],
            'account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true)
                ->whereIn('account_type_id', DB::table('accounting_account_types')->where('code', $kind === 'deduction' ? 'LIABILITY' : 'EXPENSE')->select('id')))],
            'is_active' => ['nullable', 'boolean'],
        ], ['account_id.exists' => $kind === 'deduction' ? 'A deduction is owed to a liability account.' : 'An earning is booked to an expense account.'])->validate();

        // A rate left blank means none (the screens send an empty field).
        $data['rate'] = $data['rate'] ?? 0;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateEmployee(array $input, ?Employee $employee = null): array
    {
        return Validator::make($input, [
            'code' => ['required', 'string', 'max:30', CompanyRule::unique('accounting_employees', 'code')->ignore($employee?->id)],
            'name' => ['required', 'string', 'max:160'],
            'national_id' => ['nullable', 'string', 'max:40'],
            'designation' => ['nullable', 'string', 'max:120'],
            'cost_center_id' => ['nullable', 'integer', CompanyRule::exists('accounting_cost_centers', 'id')],
            'join_date' => ['required', 'date'],
            'leave_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'salary_grade_id' => ['nullable', 'integer', CompanyRule::exists('accounting_salary_grades', 'id')],
            'base_salary' => ['required_without:salary_grade_id', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'effective_from' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:200'],
            'withhold_tax' => ['nullable', 'boolean'],
            'overtime_eligible' => ['nullable', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'components' => ['nullable', 'array', 'max:50'],
            'components.*.pay_component_id' => ['required', 'integer', 'distinct', CompanyRule::exists('accounting_pay_components', 'id')],
            'components.*.value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'schemes' => ['nullable', 'array', 'max:50'],
            'schemes.*' => ['integer', 'distinct', CompanyRule::exists('accounting_contribution_schemes', 'id')],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function saveEmployee(array $data, ?Employee $employee = null): Employee
    {
        return DB::transaction(function () use ($data, $employee): Employee {
            $fields = collect($data)->except(['components', 'schemes', 'effective_from', 'reason'])->all();

            if (($fields['base_salary'] ?? null) === null || $fields['base_salary'] === '') {
                $fields['base_salary'] = SalaryGrade::query()->findOrFail($fields['salary_grade_id'])->base_salary;
            }

            $before = $employee?->base_salary;
            $employee = $employee ? tap($employee)->update($fields) : Employee::query()->create($fields);

            if ($before !== null && Money::toCents($before) !== Money::toCents($employee->base_salary)) {
                SalaryRevision::query()->create([
                    'employee_id' => $employee->id, 'effective_from' => $data['effective_from'] ?? now()->toDateString(),
                    'old_salary' => $before, 'new_salary' => $employee->base_salary, 'reason' => $data['reason'] ?? null,
                ]);
            }

            if (array_key_exists('components', $data)) {
                EmployeeComponent::query()->where('employee_id', $employee->id)->delete();

                foreach ($data['components'] ?? [] as $row) {
                    EmployeeComponent::query()->create(['employee_id' => $employee->id, 'pay_component_id' => $row['pay_component_id'], 'value' => $row['value'] ?? null]);
                }
            }

            if (array_key_exists('schemes', $data)) {
                EmployeeScheme::query()->where('employee_id', $employee->id)->delete();

                foreach (array_unique((array) ($data['schemes'] ?? [])) as $schemeId) {
                    EmployeeScheme::query()->create(['employee_id' => $employee->id, 'contribution_scheme_id' => $schemeId]);
                }
            }

            AccountingAuditLog::record($employee, 'EMPLOYEE_SAVED', null, null, ['code' => $employee->code]);

            return $employee->refresh();
        });
    }

    public function deleteEmployee(Employee $employee): void
    {
        if (Payslip::query()->where('employee_id', $employee->id)->exists()) {
            throw new AccountingException('An employee with payslips cannot be deleted; set a leave date or deactivate instead.');
        }

        if (PayrollArrear::query()->where('employee_id', $employee->id)->exists()) {
            throw new AccountingException('An employee with arrears cannot be deleted; set a leave date or deactivate instead.');
        }

        EmployeeComponent::query()->where('employee_id', $employee->id)->delete();
        EmployeeScheme::query()->where('employee_id', $employee->id)->delete();
        $employee->delete();
    }

    public function deleteComponent(PayComponent $component): void
    {
        if (EmployeeComponent::query()->where('pay_component_id', $component->id)->exists() || SalaryGradeComponent::query()->where('pay_component_id', $component->id)->exists() || PayslipLine::query()->where('pay_component_id', $component->id)->exists()) {
            throw new AccountingException('A component in use cannot be deleted; deactivate it instead.');
        }

        $component->delete();
    }

    // -- income tax --------------------------------------------------------------------------------------------

    /**
     * Annual income tax on an annual taxable income (cents in, cents out) from the configured slabs.
     */
    public function annualTax(int $annualCents): int
    {
        $slabs = collect(config('accounting.payroll.tax_slabs', []))->sortBy('from')->values();
        $slab = $slabs->last(fn (array $row): bool => $annualCents > (int) $row['from'] * 100);

        if ($slab === null || (float) $slab['rate'] <= 0 && (float) ($slab['fixed'] ?? 0) <= 0) {
            return 0;
        }

        return (int) round((float) ($slab['fixed'] ?? 0) * 100 + ($annualCents - (int) $slab['from'] * 100) * (float) $slab['rate'] / 100);
    }

    // -- runs --------------------------------------------------------------------------------------------------

    /**
     * Start a payroll run for a month and work out the payslips of everyone employed in it.
     *
     * @throws ValidationException
     */
    public function createRun(string $month, ?string $notes = null): PayrollRun
    {
        $period = Carbon::parse($month)->startOfMonth();

        if (PayrollRun::query()->whereDate('period_month', $period->toDateString())->where('status', '<>', 'void')->exists()) {
            throw ValidationException::withMessages(['period_month' => 'There is already a payroll run for '.$period->format('F Y').'.']);
        }

        return DB::transaction(function () use ($period, $notes): PayrollRun {
            $run = PayrollRun::query()->create(['period_month' => $period->toDateString(), 'notes' => $notes]);
            $this->calculate($run);
            AccountingAuditLog::record($run, 'PAYROLL_RUN_CREATED', null, null, ['month' => $period->format('Y-m')]);

            return $run->refresh();
        });
    }

    /**
     * Work the payslips out again from the employees' current pay (draft runs only).
     */
    public function recalculate(PayrollRun $run): PayrollRun
    {
        $this->assertStatus($run, 'draft', 'Only a draft run can be recalculated.');
        DB::transaction(fn () => $this->calculate($run));

        return $run->refresh();
    }

    /**
     * Income tax withheld in a month on that month's taxable pay (cents in, cents out): the annual slabs, spread over 12 months.
     */
    public function monthlyTax(int $monthlyTaxableCents): int
    {
        return (int) round($this->annualTax($monthlyTaxableCents * 12) / 12);
    }

    /**
     * What one component pays in cents. Fixed amounts and quantity x rate follow the days worked; a percent follows the basic.
     */
    private function componentCents(PayComponent $component, float $value, int $basic, float $factor): int
    {
        return match ($component->method) {
            'percent_of_basic' => (int) round($basic * $value / 100),
            'quantity_rate' => (int) round($value * (float) $component->rate * 100 * $factor),
            default => (int) round($value * 100 * $factor),
        };
    }

    private function calculate(PayrollRun $run): void
    {
        $period = Carbon::parse($run->period_month)->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $slipIds = Payslip::query()->where('payroll_run_id', $run->id)->pluck('id');
        PayslipLine::query()->whereIn('payslip_id', $slipIds)->delete();
        Payslip::query()->where('payroll_run_id', $run->id)->delete();
        $this->releaseArrears($run);

        $salaryAccount = $this->accountByCode((string) config('accounting.payroll.salary_expense_account'), 'salary expense');
        $taxAccount = $this->accountByCode((string) config('accounting.payroll.income_tax_account'), 'income tax payable');
        $arrearsCode = config('accounting.payroll.arrears_account');
        $arrearsAccount = $arrearsCode ? $this->accountByCode((string) $arrearsCode, 'salary arrears') : $salaryAccount;
        $components = PayComponent::query()->where('is_active', true)->get()->keyBy('id');
        $assigned = EmployeeComponent::query()->get()->groupBy('employee_id');
        $gradeLinks = SalaryGradeComponent::query()->get()->groupBy('salary_grade_id');
        $revisions = SalaryRevision::query()->orderBy('effective_from')->orderBy('id')->get()->groupBy('employee_id');
        $facts = app(PayrollAttendanceService::class)->monthFacts($period);
        $loans = app(PayrollLoanService::class);
        $loansDue = $loans->dueFor($end);
        $contributions = app(PayrollContributionService::class);
        $schemes = $contributions->schemesByEmployee();
        $overtime = (array) config('accounting.payroll.overtime', []);
        $overtimeCode = $overtime['account'] ?? null;
        $overtimeAccount = $overtimeCode ? $this->accountByCode((string) $overtimeCode, 'overtime expense') : $salaryAccount;
        $arrears = PayrollArrear::query()->where('status', 'approved')->whereNull('payroll_run_id')->whereDate('payment_month', '<=', $end->toDateString())->get()->groupBy('employee_id');
        $totals = ['gross' => 0, 'deductions' => 0, 'tax' => 0, 'net' => 0, 'employer' => 0];
        $lineRows = [];
        $included = [];
        $installments = [];
        $stamp = now();

        foreach (Employee::query()->where('is_active', true)->whereDate('join_date', '<=', $end->toDateString())
            ->where(fn ($query) => $query->whereNull('leave_date')->orWhereDate('leave_date', '>=', $period->toDateString()))->orderBy('code')->get() as $employee) {
            ['cents' => $basic, 'worked' => $worked, 'days' => $days] = SalaryHistory::basicForMonth($employee, $period, $revisions[$employee->id] ?? collect());
            $unpaid = min((float) $worked, (float) ($facts[$employee->id]['unpaid'] ?? 0));

            if ($unpaid > 0 && $worked > 0) {
                $basic = (int) round($basic * ($worked - $unpaid) / $worked);
            }

            $paidDays = $worked - $unpaid;
            $factor = $paidDays / $days;
            $lines = [['pay_component_id' => null, 'kind' => 'basic', 'description' => 'Basic salary', 'cents' => $basic, 'account_id' => $salaryAccount->id, 'taxable' => true]];

            // The employee's own components first, then what the grade brings that the employee does not carry.
            $links = [];

            foreach ($assigned[$employee->id] ?? [] as $link) {
                $links[$link->pay_component_id] = $link->value;
            }

            foreach ($employee->salary_grade_id ? ($gradeLinks[$employee->salary_grade_id] ?? []) : [] as $link) {
                $links += [$link->pay_component_id => $link->value];
            }

            foreach ($links as $componentId => $override) {
                $component = $components[$componentId] ?? null;

                if ($component === null) {
                    continue;
                }

                $cents = $this->componentCents($component, (float) ($override ?? $component->value), $basic, $factor);
                $lines[] = ['pay_component_id' => $component->id, 'kind' => $component->kind, 'description' => $component->name, 'cents' => $cents, 'account_id' => $component->account_id, 'taxable' => $component->taxable];
            }

            $hours = $facts[$employee->id] ?? null;

            if ($employee->overtime_eligible && $hours !== null && ($hours['overtime'] > 0 || $hours['holiday_overtime'] > 0)) {
                $monthly = SalaryHistory::salaryOn($employee, $end, $revisions[$employee->id] ?? collect());
                $hourly = $monthly / max(1.0, (float) ($overtime['hours_per_month'] ?? 208));
                $cents = (int) round($hourly * ($hours['overtime'] * (float) ($overtime['multiplier'] ?? 2) + $hours['holiday_overtime'] * (float) ($overtime['holiday_multiplier'] ?? 2)));
                $label = rtrim(rtrim(number_format($hours['overtime'], 2, '.', ''), '0'), '.').' h'.($hours['holiday_overtime'] > 0 ? ' + '.rtrim(rtrim(number_format($hours['holiday_overtime'], 2, '.', ''), '0'), '.').' h holiday' : '');
                $lines[] = ['pay_component_id' => null, 'kind' => 'earning', 'description' => "Overtime ({$label})", 'cents' => $cents, 'account_id' => $overtimeAccount->id, 'taxable' => true];
            }

            $taxable = collect($lines)->whereIn('kind', ['basic', 'earning'])->where('taxable', true)->sum('cents');
            $tax = $employee->withhold_tax ? $this->monthlyTax($taxable) : 0;
            $arrearsCents = 0;
            $arrearsTax = 0;
            $owed = $arrears[$employee->id] ?? collect();

            if ($owed->isNotEmpty()) {
                $arrearsCents = $owed->sum(fn (PayrollArrear $row): int => Money::toCents($row->amount));
                $first = $owed->min(fn (PayrollArrear $row) => $row->from_month->toDateString());
                $last = $owed->max(fn (PayrollArrear $row) => $row->to_month->toDateString());
                $label = Carbon::parse($first)->format('M Y').(Carbon::parse($first)->isSameMonth(Carbon::parse($last)) ? '' : ' - '.Carbon::parse($last)->format('M Y'));
                $lines[] = ['pay_component_id' => null, 'kind' => 'arrears', 'description' => "Arrears ({$label})", 'cents' => $arrearsCents, 'account_id' => $arrearsAccount->id, 'taxable' => true];
                $arrearsTax = $employee->withhold_tax ? $owed->sum(fn (PayrollArrear $row): int => $this->arrearsTax($row)) : 0;
                $included = array_merge($included, $owed->pluck('id')->all());
            }

            $earned = collect($lines)->whereIn('kind', ['basic', 'earning'])->sum('cents');
            $employer = 0;

            foreach ($schemes[$employee->id] ?? [] as $scheme) {
                $parts = [[$contributions->amounts($scheme, $basic, $earned), $scheme->name]];

                if ($scheme->on_arrears && $arrearsCents > 0 && $scheme->base !== 'fixed') {
                    $parts[] = [['employee' => (int) round($arrearsCents * (float) $scheme->employee_rate / 100), 'employer' => (int) round($arrearsCents * (float) $scheme->employer_rate / 100)], $scheme->name.' on arrears'];
                }

                foreach ($parts as [$amount, $label]) {
                    if ($amount['employee'] > 0 && $scheme->employee_account_id) {
                        $lines[] = ['pay_component_id' => null, 'kind' => 'deduction', 'description' => $label, 'cents' => $amount['employee'], 'account_id' => $scheme->employee_account_id, 'taxable' => false];
                    }

                    if ($amount['employer'] > 0 && $scheme->employer_expense_account_id && $scheme->employer_liability_account_id) {
                        $lines[] = ['pay_component_id' => null, 'kind' => 'employer', 'description' => $label.' (employer)', 'cents' => $amount['employer'], 'account_id' => $scheme->employer_expense_account_id, 'taxable' => false];
                        $lines[] = ['pay_component_id' => null, 'kind' => 'employer_due', 'description' => $label.' (employer, owed)', 'cents' => $amount['employer'], 'account_id' => $scheme->employer_liability_account_id, 'taxable' => false];
                        $employer += $amount['employer'];
                    }
                }
            }

            foreach ($loansDue[$employee->id] ?? [] as $due) {
                $lines[] = ['pay_component_id' => null, 'kind' => 'deduction', 'description' => ($due['kind'] === 'advance' ? 'Advance recovery' : "Loan instalment {$due['number']}/{$due['of']}"), 'cents' => Money::toCents($due['installment']->amount), 'account_id' => $loans->accountId(), 'taxable' => false];
                $installments[] = $due['installment']->id;
            }

            $gross = collect($lines)->whereIn('kind', ['basic', 'earning', 'arrears'])->sum('cents');
            $deductions = collect($lines)->where('kind', 'deduction')->sum('cents');
            $net = $gross - $deductions - $tax - $arrearsTax;

            if ($net < 0) {
                throw new AccountingException("{$employee->name} would be paid a negative net salary ({$this->fmt($net)}): reduce the deductions.");
            }

            if ($tax > 0) {
                $lines[] = ['pay_component_id' => null, 'kind' => 'tax', 'description' => 'Income tax', 'cents' => $tax, 'account_id' => $taxAccount->id, 'taxable' => false];
            }

            if ($arrearsTax > 0) {
                $lines[] = ['pay_component_id' => null, 'kind' => 'tax', 'description' => 'Income tax on arrears', 'cents' => $arrearsTax, 'account_id' => $taxAccount->id, 'taxable' => false];
            }

            $slip = Payslip::query()->create([
                'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'basic' => Money::fromCents($basic), 'gross' => Money::fromCents($gross), 'deductions' => Money::fromCents($deductions),
                'tax' => Money::fromCents($tax + $arrearsTax), 'net' => Money::fromCents($net), 'employer' => Money::fromCents($employer), 'days_paid' => $paidDays, 'days_in_month' => $days,
            ]);

            foreach ($lines as $line) {
                if ($line['cents'] !== 0) {
                    $lineRows[] = ['payslip_id' => $slip->id, 'pay_component_id' => $line['pay_component_id'], 'kind' => $line['kind'], 'description' => $line['description'], 'amount' => Money::fromCents($line['cents']), 'account_id' => $line['account_id'], 'created_at' => $stamp, 'updated_at' => $stamp];
                }
            }

            $totals['gross'] += $gross;
            $totals['deductions'] += $deductions;
            $totals['tax'] += $tax + $arrearsTax;
            $totals['net'] += $net;
            $totals['employer'] += $employer;
        }

        foreach (array_chunk($lineRows, 500) as $chunk) {
            PayslipLine::query()->insert($chunk);
        }

        $loans->attach($installments, $run);

        if ($included !== []) {
            PayrollArrear::query()->whereIn('id', $included)->update(['payroll_run_id' => $run->id, 'status' => 'included']);
        }

        $run->forceFill(array_map(fn (int $cents) => Money::fromCents($cents), $totals))->save();
    }

    /**
     * Tax on back pay is worked out as if it had been paid in the months it belongs to: for each month, the tax on that
     * month's taxable pay with the arrears added, less the tax already withheld on that month's pay.
     */
    public function arrearsTax(PayrollArrear $arrear): int
    {
        $tax = 0;

        foreach ($arrear->months() as $month) {
            $tax += $this->monthlyTax($month['taxable'] + $month['difference']) - $this->monthlyTax($month['taxable']);
        }

        return max(0, $tax);
    }

    /**
     * Back pay that was part of a run (voided, deleted or recalculated) goes back to the approved queue.
     */
    private function releaseArrears(PayrollRun $run): void
    {
        PayrollArrear::query()->where('payroll_run_id', $run->id)->update(['payroll_run_id' => null, 'status' => 'approved']);
        app(PayrollLoanService::class)->release($run);
    }

    /**
     * Book the run: expense for basic pay and earnings (by account and cost center), liabilities for deductions and
     * tax, and the net pay owed to employees.
     */
    public function post(PayrollRun $run, ?int $payableAccountId = null, ?string $date = null): PayrollRun
    {
        return DB::transaction(function () use ($run, $payableAccountId, $date): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            $this->assertStatus($run, 'draft', 'Only a draft run can be posted.');
            $slips = Payslip::query()->where('payroll_run_id', $run->id)->get();

            if ($slips->isEmpty() || Money::toCents($run->net) === 0 && Money::toCents($run->gross) === 0) {
                throw new AccountingException('The run has no pay to post.');
            }

            $payable = $payableAccountId
                ? ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->findOrFail($payableAccountId)
                : $this->accountByCode((string) config('accounting.payroll.net_payable_account'), 'net salary payable');
            $costCenters = Employee::query()->whereIn('id', $slips->pluck('employee_id'))->pluck('cost_center_id', 'id');
            $debits = [];
            $credits = [];

            foreach (PayslipLine::query()->whereIn('payslip_id', $slips->pluck('id'))->get() as $line) {
                $cents = Money::toCents($line->amount);

                if (in_array($line->kind, ['basic', 'earning', 'arrears', 'employer'], true)) {
                    $center = (int) ($costCenters[$slips->firstWhere('id', $line->payslip_id)->employee_id] ?? 0);
                    $debits[$line->account_id.'|'.$center] = ($debits[$line->account_id.'|'.$center] ?? 0) + $cents;
                } else {
                    $credits[$line->account_id] = ($credits[$line->account_id] ?? 0) + $cents;
                }
            }

            $lines = [];

            foreach ($debits as $key => $cents) {
                [$account, $center] = array_map('intval', explode('|', $key));
                $lines[] = ['chart_of_account_id' => $account, 'cost_center_id' => $center ?: null, 'debit' => Money::fromCents($cents), 'credit' => 0];
            }

            foreach ($credits as $account => $cents) {
                $lines[] = ['chart_of_account_id' => $account, 'debit' => 0, 'credit' => Money::fromCents($cents)];
            }

            $lines[] = ['chart_of_account_id' => $payable->id, 'debit' => 0, 'credit' => $run->net, 'description' => 'Net salaries'];
            $month = Carbon::parse($run->period_month);
            $posted = Carbon::parse($date ?? $month->copy()->endOfMonth())->toDateString();
            $entry = $this->journals->create([
                'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                'origin_module' => self::ORIGIN,
                'entry_date' => $posted,
                'reference' => 'PAYROLL-'.$month->format('Y-m'),
                'description' => 'Salaries for '.$month->format('F Y'),
                'lines' => $lines,
                'auto_post' => true,
                'system_generated' => true,
            ]);
            $run->forceFill(['status' => 'posted', 'payable_account_id' => $payable->id, 'journal_entry_id' => $entry->id, 'posted_on' => $posted])->save();
            app(PayrollLoanService::class)->closeRecovered($run);
            AccountingAuditLog::record($run, 'PAYROLL_RUN_POSTED', null, null, ['month' => $month->format('Y-m'), 'net' => $run->net, 'journal_entry_id' => $entry->id]);

            return $run->refresh();
        });
    }

    /**
     * Pay the net salaries out of a bank or cash account.
     */
    public function pay(PayrollRun $run, int $fromAccountId, ?string $date = null): PayrollRun
    {
        return DB::transaction(function () use ($run, $fromAccountId, $date): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            $this->assertStatus($run, 'posted', 'Only a posted run can be paid.');
            $from = ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->findOrFail($fromAccountId);
            $paid = Carbon::parse($date ?? now())->toDateString();
            $month = Carbon::parse($run->period_month);
            $entry = $this->journals->create([
                'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                'origin_module' => self::ORIGIN,
                'entry_date' => $paid,
                'reference' => 'SALARY-PAID-'.$month->format('Y-m'),
                'description' => 'Salaries paid for '.$month->format('F Y'),
                'lines' => [
                    ['chart_of_account_id' => $run->payable_account_id, 'debit' => $run->net, 'credit' => 0],
                    ['chart_of_account_id' => $from->id, 'debit' => 0, 'credit' => $run->net],
                ],
                'auto_post' => true,
                'system_generated' => true,
            ]);
            $run->forceFill(['status' => 'paid', 'payment_entry_id' => $entry->id, 'paid_on' => $paid])->save();
            AccountingAuditLog::record($run, 'PAYROLL_RUN_PAID', null, null, ['month' => $month->format('Y-m'), 'net' => $run->net, 'journal_entry_id' => $entry->id]);

            return $run->refresh();
        });
    }

    /**
     * Cancel a posted or paid run: the payment and the salary entry are reversed. A draft run is deleted instead.
     */
    public function void(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! in_array($run->status, ['posted', 'paid'], true)) {
                throw new AccountingException('Only a posted or paid run can be voided.');
            }

            $label = Carbon::parse($run->period_month)->format('F Y');

            foreach ([$run->payment_entry_id, $run->journal_entry_id] as $entryId) {
                if ($entryId !== null) {
                    $this->journals->reverse(JournalEntry::query()->findOrFail($entryId), "Void of payroll for {$label}");
                }
            }

            $run->forceFill(['status' => 'void'])->save();
            $this->releaseArrears($run);
            AccountingAuditLog::record($run, 'PAYROLL_RUN_VOIDED', null, null, ['month' => Carbon::parse($run->period_month)->format('Y-m')]);

            return $run->refresh();
        });
    }

    public function deleteRun(PayrollRun $run): void
    {
        $this->assertStatus($run, 'draft', 'Only a draft run can be deleted; void a posted run instead.');
        DB::transaction(function () use ($run): void {
            $this->releaseArrears($run);
            PayslipLine::query()->whereIn('payslip_id', Payslip::query()->where('payroll_run_id', $run->id)->select('id'))->delete();
            Payslip::query()->where('payroll_run_id', $run->id)->delete();
            $run->delete();
        });
    }

    /**
     * Totals by month for a year (what was paid, withheld and deducted).
     *
     * @return list<array<string, mixed>>
     */
    public function summary(int $year): array
    {
        return PayrollRun::query()->whereYear('period_month', $year)->where('status', '<>', 'void')->orderBy('period_month')->get()
            ->map(fn (PayrollRun $run): array => ['id' => $run->id, 'month' => Carbon::parse($run->period_month)->format('Y-m'), 'status' => $run->status, 'gross' => $run->gross, 'deductions' => $run->deductions, 'tax' => $run->tax, 'net' => $run->net, 'employer' => $run->employer])->all();
    }

    private function assertStatus(PayrollRun $run, string $status, string $message): void
    {
        if ($run->status !== $status) {
            throw new AccountingException($message);
        }
    }

    private function accountByCode(string $code, string $label): ChartOfAccount
    {
        return ChartOfAccount::query()->where('account_code', $code)->where('is_group', false)->first()
            ?? throw new AccountingException("The {$label} account ({$code}) does not exist: set it in accounting.payroll.");
    }

    private function fmt(int $cents): string
    {
        return Money::fromCents($cents);
    }
}
