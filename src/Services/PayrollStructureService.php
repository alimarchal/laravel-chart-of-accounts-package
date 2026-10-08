<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\EmployeeComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGrade;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGradeComponent;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryRevision;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Setting pay up for many people at once: salary grades (a basic salary with its allowances), giving or taking a
 * component from a group of employees, and salary revisions with their history, singly or for a group (a 10% raise for
 * everybody, or for one grade).
 */
class PayrollStructureService
{
    // -- grades ------------------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateGrade(array $input, ?SalaryGrade $grade = null): array
    {
        return Validator::make($input, [
            'code' => ['required', 'string', 'max:30', CompanyRule::unique('accounting_salary_grades', 'code')->ignore($grade?->id)],
            'name' => ['required', 'string', 'max:120'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'is_active' => ['nullable', 'boolean'],
            'components' => ['nullable', 'array', 'max:50'],
            'components.*.pay_component_id' => ['required', 'integer', 'distinct', CompanyRule::exists('accounting_pay_components', 'id')],
            'components.*.value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function saveGrade(array $data, ?SalaryGrade $grade = null): SalaryGrade
    {
        return DB::transaction(function () use ($data, $grade): SalaryGrade {
            $fields = collect($data)->except('components')->all();
            $grade = $grade ? tap($grade)->update($fields) : SalaryGrade::query()->create($fields);

            if (array_key_exists('components', $data)) {
                SalaryGradeComponent::query()->where('salary_grade_id', $grade->id)->delete();

                foreach ($data['components'] ?? [] as $row) {
                    SalaryGradeComponent::query()->create(['salary_grade_id' => $grade->id, 'pay_component_id' => $row['pay_component_id'], 'value' => $row['value'] ?? null]);
                }
            }

            AccountingAuditLog::record($grade, 'SALARY_GRADE_SAVED', null, null, ['code' => $grade->code]);

            return $grade->refresh();
        });
    }

    public function deleteGrade(SalaryGrade $grade): void
    {
        if (Employee::query()->where('salary_grade_id', $grade->id)->exists()) {
            throw new AccountingException('A grade with employees on it cannot be deleted; move them to another grade or deactivate it.');
        }

        DB::transaction(function () use ($grade): void {
            SalaryGradeComponent::query()->where('salary_grade_id', $grade->id)->delete();
            $grade->delete();
        });
    }

    // -- groups of employees -----------------------------------------------------------------------------------

    /**
     * Who a bulk action is for: the employees listed, else everybody on a grade, else every active employee.
     *
     * @param  array<string, mixed>  $input
     * @return Collection<int, Employee>
     */
    public function employeesFor(array $input): Collection
    {
        $ids = array_filter(array_map('intval', (array) ($input['employee_ids'] ?? [])));
        $query = Employee::query()->orderBy('code');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $query->where('is_active', true);

            if (! empty($input['salary_grade_id'])) {
                $query->where('salary_grade_id', (int) $input['salary_grade_id']);
            }
        }

        return $query->get();
    }

    /**
     * Give a component to a group of employees (keeping any value they already have unless one is given) or take it away.
     *
     * @param  array<string, mixed>  $input
     * @return array{changed: int}
     *
     * @throws ValidationException
     */
    public function bulkComponent(array $input): array
    {
        $data = Validator::make($input, [
            'pay_component_id' => ['required', 'integer', CompanyRule::exists('accounting_pay_components', 'id')],
            'mode' => ['required', Rule::in(['assign', 'remove'])],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
            'salary_grade_id' => ['nullable', 'integer', CompanyRule::exists('accounting_salary_grades', 'id')],
        ])->validate();

        $employees = $this->employeesFor($data);

        if ($employees->isEmpty()) {
            throw new AccountingException('No employees match: nothing was changed.');
        }

        return DB::transaction(function () use ($data, $employees): array {
            $ids = $employees->pluck('id');

            if ($data['mode'] === 'remove') {
                $changed = EmployeeComponent::query()->whereIn('employee_id', $ids)->where('pay_component_id', $data['pay_component_id'])->delete();
            } else {
                $has = EmployeeComponent::query()->whereIn('employee_id', $ids)->where('pay_component_id', $data['pay_component_id'])->pluck('employee_id')->all();
                $changed = 0;

                foreach ($employees as $employee) {
                    if (in_array($employee->id, $has, true)) {
                        if (isset($data['value']) && $data['value'] !== '') {
                            EmployeeComponent::query()->where('employee_id', $employee->id)->where('pay_component_id', $data['pay_component_id'])->update(['value' => $data['value']]);
                            $changed++;
                        }

                        continue;
                    }

                    EmployeeComponent::query()->create(['employee_id' => $employee->id, 'pay_component_id' => $data['pay_component_id'], 'value' => $data['value'] ?? null]);
                    $changed++;
                }
            }

            $component = PayComponent::query()->findOrFail($data['pay_component_id']);
            AccountingAuditLog::record($component, 'PAYROLL_BULK_COMPONENT', null, null, ['mode' => $data['mode'], 'employees' => $employees->count(), 'changed' => $changed]);

            return ['changed' => (int) $changed];
        });
    }

    /**
     * Put a group of employees on a grade, optionally moving their salary to the grade's (recorded as a revision).
     *
     * @param  array<string, mixed>  $input
     * @return array{changed: int}
     *
     * @throws ValidationException
     */
    public function assignGrade(SalaryGrade $grade, array $input): array
    {
        $data = Validator::make($input, [
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
            'apply_salary' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
        ])->validate();
        $employees = $this->employeesFor($data);

        if ($employees->isEmpty()) {
            throw new AccountingException('No employees match: nothing was changed.');
        }

        DB::transaction(function () use ($grade, $data, $employees): void {
            foreach ($employees as $employee) {
                $employee->update(['salary_grade_id' => $grade->id]);

                if (! empty($data['apply_salary'])) {
                    $this->revise($employee, $grade->base_salary, $data['effective_from'] ?? now()->toDateString(), 'Moved to grade '.$grade->code);
                }
            }

            AccountingAuditLog::record($grade, 'SALARY_GRADE_ASSIGNED', null, null, ['employees' => $employees->count(), 'apply_salary' => ! empty($data['apply_salary'])]);
        });

        return ['changed' => $employees->count()];
    }

    // -- salary revisions --------------------------------------------------------------------------------------

    /**
     * Change an employee's monthly salary from a date. The history is kept; the salary on the employee becomes the new one.
     */
    public function revise(Employee $employee, string|float|int $newSalary, string $effectiveFrom, ?string $reason = null): ?SalaryRevision
    {
        if (Money::toCents($employee->base_salary) === Money::toCents((string) $newSalary)) {
            return null;
        }

        return DB::transaction(function () use ($employee, $newSalary, $effectiveFrom, $reason): SalaryRevision {
            $revision = SalaryRevision::query()->create([
                'employee_id' => $employee->id, 'effective_from' => $effectiveFrom, 'old_salary' => $employee->base_salary,
                'new_salary' => Money::fromCents(Money::toCents((string) $newSalary)), 'reason' => $reason,
            ]);
            $employee->update(['base_salary' => $revision->new_salary]);
            AccountingAuditLog::record($employee, 'SALARY_REVISED', ['base_salary' => $revision->old_salary], ['base_salary' => $revision->new_salary], ['effective_from' => $effectiveFrom, 'reason' => $reason]);

            return $revision;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateRevision(array $input): array
    {
        return Validator::make($input, [
            'new_salary' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:200'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateBulkRevision(array $input): array
    {
        return Validator::make($input, [
            'mode' => ['required', Rule::in(['percent', 'increase', 'set'])],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:200'],
            'round_to' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
            'salary_grade_id' => ['nullable', 'integer', CompanyRule::exists('accounting_salary_grades', 'id')],
        ])->validate();
    }

    /**
     * What a bulk raise would do, employee by employee (nothing is saved).
     *
     * @param  array<string, mixed>  $data  validated
     * @return list<array<string, mixed>>
     */
    public function previewBulkRevision(array $data): array
    {
        $round = max(1, (int) ($data['round_to'] ?? 1));
        $rows = [];

        foreach ($this->employeesFor($data) as $employee) {
            $old = Money::toCents($employee->base_salary);
            $new = match ($data['mode']) {
                'percent' => (int) round($old * (1 + (float) $data['value'] / 100)),
                'increase' => $old + Money::toCents((string) $data['value']),
                default => Money::toCents((string) $data['value']),
            };
            $new = (int) (round($new / ($round * 100)) * $round * 100);
            $rows[] = ['employee_id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'old_salary' => Money::fromCents($old), 'new_salary' => Money::fromCents($new), 'difference' => Money::fromCents($new - $old)];
        }

        return $rows;
    }

    /**
     * Apply a bulk raise: every employee whose salary changes gets a revision.
     *
     * @param  array<string, mixed>  $data  validated
     * @return array{changed: int, rows: list<array<string, mixed>>}
     */
    public function applyBulkRevision(array $data): array
    {
        $rows = $this->previewBulkRevision($data);

        if ($rows === []) {
            throw new AccountingException('No employees match: nothing was changed.');
        }

        $changed = DB::transaction(function () use ($rows, $data): int {
            $changed = 0;
            $employees = Employee::query()->whereIn('id', array_column($rows, 'employee_id'))->get()->keyBy('id');

            foreach ($rows as $row) {
                if ($this->revise($employees[$row['employee_id']], $row['new_salary'], $data['effective_from'], $data['reason'] ?? null) !== null) {
                    $changed++;
                }
            }

            return $changed;
        });

        return ['changed' => $changed, 'rows' => $rows];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(Employee $employee): array
    {
        return SalaryRevision::query()->where('employee_id', $employee->id)->orderByDesc('effective_from')->orderByDesc('id')->get()
            ->map(fn (SalaryRevision $revision): array => ['id' => $revision->id, 'effective_from' => $revision->effective_from->toDateString(), 'old_salary' => $revision->old_salary, 'new_salary' => $revision->new_salary, 'reason' => $revision->reason])->all();
    }
}
