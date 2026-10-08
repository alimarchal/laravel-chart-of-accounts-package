<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\EmployeeComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollArrear;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryRevision;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollArrearsService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollStructureService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->actingAs(tap(User::factory()->create())->assignRole('accountant'));
    $this->month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->payroll = fn () => app(PayrollService::class);
    $this->structure = fn () => app(PayrollStructureService::class);
    $this->arrears = fn () => app(PayrollArrearsService::class);
    $this->component = function (array $overrides = []): PayComponent {
        return PayComponent::query()->create(($this->payroll)()->validateComponent(['code' => 'HRA', 'name' => 'House rent', 'kind' => 'earning', 'method' => 'percent_of_basic', 'value' => 10, 'taxable' => true, 'account_id' => account('5102')->id, ...$overrides]));
    };
    $this->employee = function (array $overrides = []): Employee {
        $service = ($this->payroll)();

        return $service->saveEmployee($service->validateEmployee(['code' => 'E1', 'name' => 'Amina', 'join_date' => $this->month->copy()->subYear()->toDateString(), 'base_salary' => '100000', ...$overrides]));
    };
    $this->grade = function (array $overrides = []) {
        $service = ($this->structure)();

        return $service->saveGrade($service->validateGrade(['code' => 'G1', 'name' => 'Grade 1', 'base_salary' => '50000', ...$overrides]));
    };
    $this->slip = fn (Employee $employee, PayrollRun $run) => Payslip::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->firstOrFail();
    $this->pay = fn (int $offset) => ($this->payroll)()->post(($this->payroll)()->createRun($this->month->copy()->addMonths($offset)->toDateString()));
});

it('pays a quantity at a rate, so a new price changes everybody on the next calculation', function (): void {
    $fuel = ($this->component)(['code' => 'FUEL', 'name' => 'Fuel', 'method' => 'quantity_rate', 'value' => 75, 'rate' => 280, 'unit' => 'litre']);
    $employee = ($this->employee)(['components' => [['pay_component_id' => $fuel->id]]]);
    $other = ($this->employee)(['code' => 'E2', 'name' => 'Bilal', 'components' => [['pay_component_id' => $fuel->id, 'value' => 100]]]);

    $run = ($this->payroll)()->createRun($this->month->toDateString());
    expect(($this->slip)($employee, $run)->gross)->toBe('121000.00')->and(($this->slip)($other, $run)->gross)->toBe('128000.00');

    $fuel->update(['rate' => 300]);
    $run = ($this->payroll)()->recalculate($run);
    expect(($this->slip)($employee, $run)->gross)->toBe('122500.00')->and(($this->slip)($other, $run)->gross)->toBe('130000.00');
    expect(fn () => PayComponent::query()->create(($this->payroll)()->validateComponent(['code' => 'X', 'name' => 'X', 'kind' => 'earning', 'method' => 'quantity_rate', 'value' => 5, 'account_id' => account('5102')->id])))->toThrow(ValidationException::class);
});

it('puts employees on a grade: its salary and allowances, which the employee can still override', function (): void {
    $hra = ($this->component)();
    $fuel = ($this->component)(['code' => 'FUEL', 'name' => 'Fuel', 'method' => 'quantity_rate', 'value' => 50, 'rate' => 100]);
    $grade = ($this->grade)(['base_salary' => '60000', 'components' => [['pay_component_id' => $hra->id, 'value' => 40], ['pay_component_id' => $fuel->id]]]);
    $plain = ($this->employee)(['base_salary' => null, 'salary_grade_id' => $grade->id]);
    $custom = ($this->employee)(['code' => 'E2', 'name' => 'Bilal', 'base_salary' => '60000', 'salary_grade_id' => $grade->id, 'components' => [['pay_component_id' => $hra->id, 'value' => 10]]]);

    expect($plain->base_salary)->toBe('60000.00');
    $run = ($this->payroll)()->createRun($this->month->toDateString());
    // 60,000 + 40% of it + 50 x 100 fuel.
    expect(($this->slip)($plain, $run)->gross)->toBe('89000.00')->and(($this->slip)($custom, $run)->gross)->toBe('71000.00');

    $grade->update(['base_salary' => '70000']);
    SalaryRevision::query()->count(); // the grade's salary only applies when it is assigned
    expect(Employee::query()->find($plain->id)->base_salary)->toBe('60000.00');
    expect(fn () => ($this->structure)()->deleteGrade($grade))->toThrow(AccountingException::class, 'employees on it');
    expect(fn () => ($this->payroll)()->deleteComponent($hra))->toThrow(AccountingException::class);
});

it('assigns and removes a component for a group, and moves a group to a grade', function (): void {
    $allowance = ($this->component)(['code' => 'CONV', 'name' => 'Conveyance', 'method' => 'fixed', 'value' => 3000]);
    $grade = ($this->grade)();
    ($this->employee)();
    ($this->employee)(['code' => 'E2', 'name' => 'Bilal', 'salary_grade_id' => $grade->id]);
    ($this->employee)(['code' => 'E3', 'name' => 'Inactive', 'is_active' => false]);

    expect(($this->structure)()->bulkComponent(['pay_component_id' => $allowance->id, 'mode' => 'assign']))->toBe(['changed' => 2])
        ->and(EmployeeComponent::query()->count())->toBe(2);
    expect(($this->structure)()->bulkComponent(['pay_component_id' => $allowance->id, 'mode' => 'assign', 'value' => 4000]))->toBe(['changed' => 2])
        ->and(EmployeeComponent::query()->pluck('value')->unique()->all())->toBe(['4000.0000']);
    expect(($this->structure)()->bulkComponent(['pay_component_id' => $allowance->id, 'mode' => 'remove', 'salary_grade_id' => $grade->id]))->toBe(['changed' => 1]);
    expect(fn () => ($this->structure)()->bulkComponent(['pay_component_id' => $allowance->id, 'mode' => 'assign', 'employee_ids' => []]) && ($this->structure)()->bulkComponent(['pay_component_id' => $allowance->id, 'mode' => 'assign', 'salary_grade_id' => 999999]))->toThrow(ValidationException::class);

    $moved = ($this->structure)()->assignGrade($grade, ['employee_ids' => Employee::query()->where('code', 'E1')->pluck('id')->all(), 'apply_salary' => true, 'effective_from' => $this->month->toDateString()]);
    expect($moved)->toBe(['changed' => 1])->and(Employee::query()->firstWhere('code', 'E1')->base_salary)->toBe('50000.00')
        ->and(SalaryRevision::query()->count())->toBe(1);
});

it('keeps the history of salary changes and pays a raise from the day it takes effect', function (): void {
    $employee = ($this->employee)();
    $service = ($this->payroll)();
    $day = $this->month->copy()->addDays(9); // the raise applies from the 10th
    $service->saveEmployee($service->validateEmployee(['code' => 'E1', 'name' => 'Amina', 'join_date' => $employee->join_date->toDateString(), 'base_salary' => '130000', 'effective_from' => $day->toDateString(), 'reason' => 'Annual raise'], $employee), $employee);

    $history = ($this->structure)()->history($employee->refresh());
    expect($history)->toHaveCount(1)->and($history[0]['old_salary'])->toBe('100000.00')->and($history[0]['new_salary'])->toBe('130000.00')->and($history[0]['reason'])->toBe('Annual raise');

    $days = $this->month->daysInMonth;
    $expected = round((100000 * 9 + 130000 * ($days - 9)) / $days, 2);
    $run = $service->createRun($this->month->toDateString());
    expect((float) ($this->slip)($employee, $run)->basic)->toBe($expected);

    // Unchanged pay on a plain edit records nothing.
    $service->saveEmployee($service->validateEmployee(['code' => 'E1', 'name' => 'Amina K', 'join_date' => $employee->join_date->toDateString(), 'base_salary' => '130000'], $employee), $employee);
    expect(SalaryRevision::query()->count())->toBe(1);
});

it('previews and applies a raise for everybody, a grade or a list, with rounding', function (): void {
    $grade = ($this->grade)();
    ($this->employee)(['base_salary' => '100000']);
    ($this->employee)(['code' => 'E2', 'name' => 'Bilal', 'base_salary' => '33333', 'salary_grade_id' => $grade->id]);

    $input = ['mode' => 'percent', 'value' => 10, 'effective_from' => $this->month->toDateString(), 'round_to' => 100, 'reason' => 'Budget raise'];
    $data = ($this->structure)()->validateBulkRevision($input);
    $rows = ($this->structure)()->previewBulkRevision($data);
    expect(array_column($rows, 'new_salary'))->toBe(['110000.00', '36700.00'])->and(SalaryRevision::query()->count())->toBe(0);

    $onlyGrade = ($this->structure)()->previewBulkRevision(($this->structure)()->validateBulkRevision([...$input, 'salary_grade_id' => $grade->id]));
    expect(array_column($onlyGrade, 'code'))->toBe(['E2']);

    expect(($this->structure)()->applyBulkRevision($data)['changed'])->toBe(2)->and(SalaryRevision::query()->count())->toBe(2);
    $set = ($this->structure)()->previewBulkRevision(($this->structure)()->validateBulkRevision(['mode' => 'increase', 'value' => 5000, 'effective_from' => $this->month->toDateString()]));
    expect(array_column($set, 'new_salary'))->toBe(['115000.00', '41700.00']);
    expect(fn () => ($this->structure)()->validateBulkRevision(['mode' => 'weird', 'value' => 1, 'effective_from' => '2026-01-01']))->toThrow(ValidationException::class);
});

it('works out arrears from the payslips already posted, with the percent allowances along', function (): void {
    $hra = ($this->component)();
    $employee = ($this->employee)(['components' => [['pay_component_id' => $hra->id]]]);
    foreach ([0, 1, 2] as $offset) {
        ($this->pay)($offset);
    }

    ($this->structure)()->revise($employee, '120000', $this->month->toDateString(), 'Raise from the first month');
    $data = ($this->arrears)()->validate(['from_month' => $this->month->toDateString(), 'payment_month' => $this->month->copy()->addMonths(3)->toDateString()]);
    $rows = ($this->arrears)()->calculate($data);

    // 20,000 a month on the basic, plus the 10% allowance that follows it: 22,000 for each of 3 months.
    expect($rows)->toHaveCount(1)->and($rows[0]['amount'])->toBe('66000.00')->and($rows[0]['months'])->toHaveCount(3)->and($rows[0]['months'][0]['difference'])->toBe(2200000)
        ->and($rows[0]['from_month'])->toBe($this->month->format('Y-m'));
    expect(PayrollArrear::query()->count())->toBe(0);
    expect(fn () => ($this->arrears)()->validate(['from_month' => $this->month->toDateString(), 'payment_month' => $this->month->toDateString()]))->toThrow(ValidationException::class);
});

it('pays approved arrears as their own payslip line with the run, books them and frees them on void', function (): void {
    $employee = ($this->employee)();
    foreach ([0, 1] as $offset) {
        ($this->pay)($offset);
    }

    ($this->structure)()->revise($employee, '110000', $this->month->toDateString());
    $data = ($this->arrears)()->validate(['from_month' => $this->month->toDateString(), 'payment_month' => $this->month->copy()->addMonths(2)->toDateString()]);
    $arrear = ($this->arrears)()->create($data)[0];
    expect($arrear->status)->toBe('draft')->and($arrear->amount)->toBe('20000.00')->and(($this->arrears)()->create($data))->toBe([]);

    // A draft is not paid until approved.
    $draftRun = ($this->payroll)()->createRun($this->month->copy()->addMonths(2)->toDateString());
    expect(PayslipLine::query()->where('kind', 'arrears')->count())->toBe(0);
    ($this->payroll)()->deleteRun($draftRun);

    ($this->arrears)()->approve($arrear);
    $run = ($this->payroll)()->createRun($this->month->copy()->addMonths(2)->toDateString());
    $slip = ($this->slip)($employee, $run);
    $line = PayslipLine::query()->where('payslip_id', $slip->id)->where('kind', 'arrears')->firstOrFail();
    expect($line->amount)->toBe('20000.00')->and($line->description)->toContain('Arrears')->and($slip->gross)->toBe('130000.00')->and($slip->net)->toBe('130000.00')
        ->and($arrear->refresh()->status)->toBe('included')->and($arrear->payroll_run_id)->toBe($run->id);
    expect(fn () => ($this->arrears)()->cancel($arrear))->toThrow(AccountingException::class);

    $run = ($this->payroll)()->post($run);
    expect($run->gross)->toBe('130000.00');
    ($this->payroll)()->void($run);
    expect($arrear->refresh()->status)->toBe('approved')->and($arrear->payroll_run_id)->toBeNull();

    $again = ($this->payroll)()->createRun($this->month->copy()->addMonths(2)->toDateString());
    expect(($this->slip)($employee, $again)->gross)->toBe('130000.00')->and($arrear->refresh()->status)->toBe('included');
});

it('taxes arrears as if they were paid in the months they belong to, not as one big month', function (): void {
    $employee = ($this->employee)(['base_salary' => '200000', 'withhold_tax' => true]);
    foreach ([0, 1, 2] as $offset) {
        ($this->pay)($offset);
    }

    ($this->structure)()->revise($employee, '230000', $this->month->toDateString());
    $arrear = ($this->arrears)()->create(($this->arrears)()->validate(['from_month' => $this->month->toDateString(), 'payment_month' => $this->month->copy()->addMonths(3)->toDateString()]))[0];
    ($this->arrears)()->approve($arrear);
    $run = ($this->payroll)()->createRun($this->month->copy()->addMonths(3)->toDateString());
    $slip = ($this->slip)($employee, $run);

    $service = ($this->payroll)();
    $perMonth = $service->monthlyTax(23000000) - $service->monthlyTax(20000000);
    $asOneMonth = $service->monthlyTax(23000000 + 9000000) - $service->monthlyTax(23000000);
    $lines = PayslipLine::query()->where('payslip_id', $slip->id)->where('kind', 'tax')->orderBy('id')->pluck('amount', 'description')->all();

    expect($arrear->amount)->toBe('90000.00')->and((int) round((float) $lines['Income tax on arrears'] * 100))->toBe($perMonth * 3)->and($perMonth * 3)->toBeLessThan($asOneMonth)
        ->and((int) round((float) $lines['Income tax'] * 100))->toBe($service->monthlyTax(23000000));
});

it('reports the register with totals and lets arrears be cancelled or approved in one go', function (): void {
    $employee = ($this->employee)();
    ($this->pay)(0);
    ($this->structure)()->revise($employee, '105000', $this->month->toDateString());
    $created = ($this->arrears)()->create(($this->arrears)()->validate(['from_month' => $this->month->toDateString(), 'payment_month' => $this->month->copy()->addMonth()->toDateString()]));

    $report = ($this->arrears)()->report();
    expect($report['rows'])->toHaveCount(1)->and($report['totals']['amount'])->toBe('5000.00')->and($report['totals']['by_status'])->toBe(['draft' => '5000.00'])
        ->and($report['rows'][0]['months'][0]['difference'])->toBe('5000.00');
    expect(($this->arrears)()->approveAll())->toBe(1)->and(($this->arrears)()->report('approved')['rows'])->toHaveCount(1);
    expect(($this->arrears)()->cancel($created[0])->status)->toBe('cancelled')->and(($this->arrears)()->report()['totals']['count'])->toBe(0);
    // Cancelled months can be claimed again.
    expect(($this->arrears)()->create(($this->arrears)()->validate(['from_month' => $this->month->toDateString(), 'payment_month' => $this->month->copy()->addMonth()->toDateString()])))->toHaveCount(1);
});
