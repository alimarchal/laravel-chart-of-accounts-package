<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ContributionScheme;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\Loan;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollAdjustment;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollArrear;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGrade;
use Alimarchal\LaravelChartOfAccounts\Models\Settlement;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->user = tap(User::factory()->create())->assignRole('accountant');
    Sanctum::actingAs($this->user);
    $this->month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $payroll = app(PayrollService::class);
    $this->employee = fn (string $code, string $name, array $extra = []) => $payroll->saveEmployee($payroll->validateEmployee(['code' => $code, 'name' => $name, 'join_date' => $this->month->copy()->subYear()->toDateString(), 'base_salary' => '50000', ...$extra]));
    $this->amina = ($this->employee)('E1', 'Amina Khan', ['withhold_tax' => true, 'designation' => 'Accountant', 'national_id' => '35202-1111111-1']);
    $this->bilal = ($this->employee)('E2', 'Bilal Ahmed', ['designation' => 'Driver', 'base_salary' => '30000', 'withhold_tax' => false]);
    $this->carol = ($this->employee)('E3', 'Carol 100%', ['withhold_tax' => true, 'designation' => 'Manager', 'base_salary' => '90000']);
    $this->carol->forceFill(['is_active' => false])->save();
    $this->ids = fn (string $url, string $key = 'id') => collect($this->getJson($url)->assertOk()->json('data'))->pluck($key)->all();
});

it('filters employees by text, status, tax, dates and salary', function (): void {
    $codes = fn (string $query) => ($this->ids)("/api/v1/accounting/payroll/employees?{$query}", 'code');

    expect($codes(''))->toBe(['E1', 'E2', 'E3'])
        ->and($codes('search=khan'))->toBe(['E1'])                       // name
        ->and($codes('search=35202'))->toBe(['E1'])                      // national ID
        ->and($codes('search=driver'))->toBe(['E2'])                     // designation
        ->and($codes('search=100%25'))->toBe(['E3'])                     // % is not a wildcard
        ->and($codes('active=0'))->toBe(['E3'])
        ->and($codes('filter[active]=true'))->toBe(['E1', 'E2'])
        ->and($codes('withhold_tax=0'))->toBe(['E2'])
        ->and($codes('min_salary=40000'))->toBe(['E1', 'E3'])
        ->and($codes('max_salary=50000&min_salary=40000'))->toBe(['E1'])
        ->and($codes('sort=-base_salary'))->toBe(['E3', 'E1', 'E2'])
        ->and($codes('joined_from='.$this->month->toDateString()))->toBe([])           // all joined a year before
        ->and($codes('joined_to='.$this->month->toDateString()))->toBe(['E1', 'E2', 'E3']);
    $this->getJson('/api/v1/accounting/payroll/employees?max_salary=lots')->assertStatus(422);
});

it('filters payroll runs by status, month and notes', function (): void {
    $run = fn (int $months, string $status, string $notes) => tap(PayrollRun::query()->create(['period_month' => $this->month->copy()->addMonths($months)->toDateString(), 'notes' => $notes]), fn (PayrollRun $row) => $row->forceFill(['status' => $status])->save());
    $first = $run(0, 'posted', 'January regular');
    $second = $run(1, 'draft', 'February bonus run');
    $third = $run(2, 'posted', 'March regular');

    expect(($this->ids)('/api/v1/accounting/payroll'))->toBe([$third->id, $second->id, $first->id])
        ->and(($this->ids)('/api/v1/accounting/payroll?status=posted'))->toBe([$third->id, $first->id])
        ->and(($this->ids)('/api/v1/accounting/payroll?search=bonus'))->toBe([$second->id])
        ->and(($this->ids)('/api/v1/accounting/payroll?period_from='.$this->month->copy()->addMonth()->toDateString()))->toBe([$third->id, $second->id])
        ->and(($this->ids)('/api/v1/accounting/payroll?period_to='.$this->month->toDateString().'&status=posted'))->toBe([$first->id]);
});

it('filters loans, adjustments, arrears and settlements by employee name or code', function (): void {
    $loan = fn (Employee $employee, string $kind, string $status, string $principal, int $day) => Loan::query()->forceCreate([
        'employee_id' => $employee->id, 'kind' => $kind, 'principal' => $principal, 'installments' => 4, 'start_month' => $this->month->toDateString(), 'issued_on' => $this->month->copy()->addDays($day)->toDateString(), 'status' => $status, 'company_id' => $employee->company_id,
    ]);
    $one = $loan($this->amina, 'loan', 'active', '40000', 1);
    $two = $loan($this->bilal, 'advance', 'draft', '5000', 10);

    expect(($this->ids)('/api/v1/accounting/payroll/loans?search=amina'))->toBe([$one->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/loans?search=E2'))->toBe([$two->id])                      // employee code
        ->and(($this->ids)('/api/v1/accounting/payroll/loans?employee_id='.$this->bilal->id))->toBe([$two->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/loans?kind=advance'))->toBe([$two->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/loans?status=active'))->toBe([$one->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/loans?min_principal=10000'))->toBe([$one->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/loans?issued_from='.$this->month->copy()->addDays(5)->toDateString()))->toBe([$two->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/loans?search=amina&kind=advance'))->toBe([]);           // AND, not OR

    $adjustment = fn (Employee $employee, string $kind, bool $taxable, string $description) => PayrollAdjustment::query()->forceCreate([
        'employee_id' => $employee->id, 'month' => $this->month->toDateString(), 'kind' => $kind, 'description' => $description, 'amount' => '1000', 'taxable' => $taxable, 'status' => 'open', 'company_id' => $employee->company_id,
    ]);
    $bonus = $adjustment($this->amina, 'earning', true, 'Eid bonus');
    $fine = $adjustment($this->bilal, 'deduction', false, 'Late fine');
    $month = $this->month->format('Y-m');
    expect(($this->ids)("/api/v1/accounting/payroll/adjustments?month={$month}&search=eid"))->toBe([$bonus->id])
        ->and(($this->ids)("/api/v1/accounting/payroll/adjustments?month={$month}&search=bilal"))->toBe([$fine->id])
        ->and(($this->ids)("/api/v1/accounting/payroll/adjustments?month={$month}&kind=deduction"))->toBe([$fine->id])
        ->and(($this->ids)("/api/v1/accounting/payroll/adjustments?month={$month}&taxable=0"))->toBe([$fine->id])
        ->and(($this->ids)("/api/v1/accounting/payroll/adjustments?month={$month}&employee_id=".$this->amina->id))->toBe([$bonus->id]);

    $arrear = fn (Employee $employee, string $status) => PayrollArrear::query()->forceCreate([
        'employee_id' => $employee->id, 'from_month' => $this->month->toDateString(), 'to_month' => $this->month->toDateString(), 'payment_month' => $this->month->toDateString(), 'amount' => '500', 'breakdown' => '[]', 'status' => $status, 'company_id' => $employee->company_id,
    ]);
    $draft = $arrear($this->amina, 'draft');
    $approved = $arrear($this->bilal, 'approved');
    expect(($this->ids)('/api/v1/accounting/payroll/arrears?search=bilal'))->toBe([$approved->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/arrears?employee_id='.$this->amina->id))->toBe([$draft->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/arrears?status=approved&search=amina'))->toBe([]);

    $settlement = fn (Employee $employee, string $status, string $net, int $day) => Settlement::query()->forceCreate([
        'employee_id' => $employee->id, 'leave_date' => $this->month->copy()->addDays($day)->toDateString(), 'status' => $status, 'service_years' => '3', 'basic' => '50000', 'gratuity' => $net, 'leave_days' => '0', 'leave_encashment' => '0',
        'adjustment' => '0', 'loan_recovery' => '0', 'net' => $net, 'breakdown' => '[]', 'company_id' => $employee->company_id,
    ]);
    $small = $settlement($this->amina, 'draft', '10000', 3);
    $large = $settlement($this->bilal, 'posted', '90000', 20);
    expect(($this->ids)('/api/v1/accounting/payroll/settlements'))->toBe([$large->id, $small->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/settlements?status=posted'))->toBe([$large->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/settlements?search=amina'))->toBe([$small->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/settlements?min_net=50000'))->toBe([$large->id])
        ->and(($this->ids)('/api/v1/accounting/payroll/settlements?leave_to='.$this->month->copy()->addDays(10)->toDateString()))->toBe([$small->id]);
});

it('filters pay components, salary grades and contribution schemes', function (): void {
    $component = fn (string $code, string $name, string $kind, string $method, bool $taxable) => PayComponent::query()->forceCreate(['code' => $code, 'name' => $name, 'kind' => $kind, 'method' => $method, 'value' => 100, 'taxable' => $taxable, 'is_active' => true, 'account_id' => account('5101')->id, 'company_id' => $this->amina->company_id]);
    $house = $component('HRA', 'House rent', 'earning', 'percent_of_basic', true);
    $fine = $component('FINE', 'Late fine', 'deduction', 'fixed', false);

    expect(($this->ids)('/api/v1/accounting/payroll/components?search=rent', 'code'))->toBe(['HRA'])
        ->and(($this->ids)('/api/v1/accounting/payroll/components?kind=deduction', 'code'))->toBe(['FINE'])
        ->and(($this->ids)('/api/v1/accounting/payroll/components?method=percent_of_basic', 'code'))->toBe(['HRA'])
        ->and(($this->ids)('/api/v1/accounting/payroll/components?taxable=0', 'code'))->toBe(['FINE'])
        ->and(($this->ids)('/api/v1/accounting/payroll/components?filter[kind]=earning,deduction&sort=-code', 'code'))->toBe(['HRA', 'FINE'])
        ->and(($this->ids)('/api/v1/accounting/payroll/components?search=zzz', 'code'))->toBe([]);

    $grade = fn (string $code, string $name, string $salary, bool $active) => SalaryGrade::query()->forceCreate(['code' => $code, 'name' => $name, 'base_salary' => $salary, 'is_active' => $active, 'company_id' => $this->amina->company_id]);
    $grade('G1', 'Junior', '30000', true);
    $grade('G2', 'Senior', '80000', true);
    $grade('G3', 'Retired', '60000', false);
    expect(($this->ids)('/api/v1/accounting/payroll/grades?search=senior', 'code'))->toBe(['G2'])
        ->and(($this->ids)('/api/v1/accounting/payroll/grades?min_salary=50000', 'code'))->toBe(['G2', 'G3'])
        ->and(($this->ids)('/api/v1/accounting/payroll/grades?min_salary=50000&active=1', 'code'))->toBe(['G2'])
        ->and(($this->ids)('/api/v1/accounting/payroll/grades?sort=-base_salary', 'code'))->toBe(['G2', 'G3', 'G1']);

    $scheme = fn (string $code, string $name, string $base, bool $all, bool $active) => ContributionScheme::query()->forceCreate(['code' => $code, 'name' => $name, 'base' => $base, 'employee_rate' => 1, 'employer_rate' => 1, 'employee_fixed' => 0, 'employer_fixed' => 0, 'applies_to_all' => $all, 'is_active' => $active, 'employee_account_id' => account('2103')->id, 'employer_expense_account_id' => account('5101')->id, 'employer_liability_account_id' => account('2103')->id, 'company_id' => $this->amina->company_id]);
    $scheme('EOBI', 'Old age benefit', 'fixed', true, true);
    $scheme('PF', 'Provident fund', 'basic', false, true);
    expect(($this->ids)('/api/v1/accounting/payroll/schemes?search=provident', 'code'))->toBe(['PF'])
        ->and(($this->ids)('/api/v1/accounting/payroll/schemes?base=fixed', 'code'))->toBe(['EOBI'])
        ->and(($this->ids)('/api/v1/accounting/payroll/schemes?applies_to_all=0', 'code'))->toBe(['PF']);
});
