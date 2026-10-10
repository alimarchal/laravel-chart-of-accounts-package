<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ContributionScheme;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\EmployeeScheme;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contribution schemes (EOBI, PESSI/SESSI, provident fund): what is taken from the employee and what the employer adds,
 * as a rate of the basic or the gross up to a ceiling, or a fixed amount. The employee's share is a deduction from pay owed
 * to the fund; the employer's share is an expense owed to the fund, outside the net pay.
 */
class PayrollContributionService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?ContributionScheme $scheme = null): array
    {
        $account = fn (string $type) => ['nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true)
            ->whereIn('account_type_id', DB::table('accounting_account_types')->where('code', $type)->select('id')))];
        $data = Validator::make($input, [
            'code' => ['required', 'string', 'max:30', CompanyRule::unique('accounting_contribution_schemes', 'code')->ignore($scheme?->id)],
            'name' => ['required', 'string', 'max:120'],
            'base' => ['required', Rule::in(array_keys(ContributionScheme::BASES))],
            'employee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employer_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employee_fixed' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'employer_fixed' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'ceiling' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'employee_account_id' => $account('LIABILITY'),
            'employer_expense_account_id' => $account('EXPENSE'),
            'employer_liability_account_id' => $account('LIABILITY'),
            'on_arrears' => ['nullable', 'boolean'],
            'applies_to_all' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        // Amounts left blank mean none (the screens send empty fields).
        foreach (['employee_rate', 'employer_rate', 'employee_fixed', 'employer_fixed'] as $key) {
            if (blank($data[$key] ?? null)) {
                $data[$key] = 0;
            }
        }

        $fixed = $data['base'] === 'fixed';
        $employee = (float) ($fixed ? $data['employee_fixed'] : $data['employee_rate']);
        $employer = (float) ($fixed ? $data['employer_fixed'] : $data['employer_rate']);
        $errors = [];

        if ($employee <= 0 && $employer <= 0) {
            $errors['employee_rate'] = 'Give an employee or an employer contribution.';
        }

        if ($employee > 0 && empty($data['employee_account_id'])) {
            $errors['employee_account_id'] = 'Choose the liability account the employees\' share is owed to.';
        }

        if ($employer > 0 && (empty($data['employer_expense_account_id']) || empty($data['employer_liability_account_id']))) {
            $errors['employer_expense_account_id'] = 'Choose the expense account and the liability account of the employer\'s share.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    public function save(array $data, ?ContributionScheme $scheme = null): ContributionScheme
    {
        $scheme = $scheme ? tap($scheme)->update($data) : ContributionScheme::query()->create($data);
        AccountingAuditLog::record($scheme, 'CONTRIBUTION_SCHEME_SAVED', null, null, ['code' => $scheme->code]);

        return $scheme->refresh();
    }

    public function delete(ContributionScheme $scheme): void
    {
        DB::transaction(function () use ($scheme): void {
            EmployeeScheme::query()->where('contribution_scheme_id', $scheme->id)->delete();
            $scheme->delete();
        });
    }

    /**
     * Give a scheme to employees or take it away: those listed, else every active employee.
     *
     * @param  array<string, mixed>  $input
     * @return array{changed: int}
     *
     * @throws ValidationException
     */
    public function assign(ContributionScheme $scheme, array $input): array
    {
        $data = Validator::make($input, [
            'mode' => ['nullable', Rule::in(['assign', 'remove'])],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
        ])->validate();
        $ids = array_filter(array_map('intval', (array) ($data['employee_ids'] ?? [])));
        $employees = Employee::query()->when($ids !== [], fn ($query) => $query->whereIn('id', $ids), fn ($query) => $query->where('is_active', true))->pluck('id');

        if ($employees->isEmpty()) {
            throw new AccountingException('No employees match: nothing was changed.');
        }

        if (($data['mode'] ?? 'assign') === 'remove') {
            return ['changed' => (int) EmployeeScheme::query()->where('contribution_scheme_id', $scheme->id)->whereIn('employee_id', $employees)->delete()];
        }

        $has = EmployeeScheme::query()->where('contribution_scheme_id', $scheme->id)->whereIn('employee_id', $employees)->pluck('employee_id')->all();
        $new = $employees->reject(fn ($id) => in_array($id, $has, true));

        foreach ($new as $id) {
            EmployeeScheme::query()->create(['employee_id' => $id, 'contribution_scheme_id' => $scheme->id]);
        }

        return ['changed' => $new->count()];
    }

    /**
     * The schemes that apply to each employee: those meant for everybody and those given to them.
     *
     * @return array<int, Collection<int, ContributionScheme>>
     */
    public function schemesByEmployee(): array
    {
        $schemes = ContributionScheme::query()->where('is_active', true)->get()->keyBy('id');

        if ($schemes->isEmpty()) {
            return [];
        }

        $everyone = $schemes->where('applies_to_all', true)->all();
        $given = EmployeeScheme::query()->whereIn('contribution_scheme_id', $schemes->keys())->get()->groupBy('employee_id');
        $result = [];

        foreach (Employee::query()->where('is_active', true)->pluck('id') as $id) {
            $mine = $everyone;

            foreach ($given[$id] ?? [] as $link) {
                if (isset($schemes[$link->contribution_scheme_id])) {
                    $mine[$link->contribution_scheme_id] = $schemes[$link->contribution_scheme_id];
                }
            }

            if ($mine !== []) {
                $result[$id] = collect(array_values($mine));
            }
        }

        return $result;
    }

    /**
     * What a scheme takes and adds for one employee in a month (cents): on the basic or the gross, up to the ceiling, or fixed.
     *
     * @return array{employee: int, employer: int}
     */
    public function amounts(ContributionScheme $scheme, int $basic, int $gross): array
    {
        if ($scheme->base === 'fixed') {
            return ['employee' => Money::toCents($scheme->employee_fixed), 'employer' => Money::toCents($scheme->employer_fixed)];
        }

        $base = $scheme->base === 'gross' ? $gross : $basic;

        if ($scheme->ceiling !== null) {
            $base = min($base, Money::toCents($scheme->ceiling));
        }

        return ['employee' => (int) round($base * (float) $scheme->employee_rate / 100), 'employer' => (int) round($base * (float) $scheme->employer_rate / 100)];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ContributionScheme $scheme): array
    {
        return [
            'id' => $scheme->id, 'code' => $scheme->code, 'name' => $scheme->name, 'base' => $scheme->base, 'employee_rate' => $scheme->employee_rate, 'employer_rate' => $scheme->employer_rate,
            'employee_fixed' => $scheme->employee_fixed, 'employer_fixed' => $scheme->employer_fixed, 'ceiling' => $scheme->ceiling, 'employee_account_id' => $scheme->employee_account_id,
            'employer_expense_account_id' => $scheme->employer_expense_account_id, 'employer_liability_account_id' => $scheme->employer_liability_account_id,
            'on_arrears' => $scheme->on_arrears, 'applies_to_all' => $scheme->applies_to_all, 'is_active' => $scheme->is_active,
            'employees' => EmployeeScheme::query()->where('contribution_scheme_id', $scheme->id)->count(),
        ];
    }
}
