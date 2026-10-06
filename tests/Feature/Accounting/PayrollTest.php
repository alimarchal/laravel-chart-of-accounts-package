<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    $this->month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->service = fn () => app(PayrollService::class);
    $this->component = function (array $overrides = []): PayComponent {
        $service = ($this->service)();

        return PayComponent::query()->create($service->validateComponent(['code' => 'HRA', 'name' => 'House rent', 'kind' => 'earning', 'method' => 'percent_of_basic', 'value' => 10, 'taxable' => true, 'account_id' => account('5102')->id, ...$overrides]));
    };
    $this->employee = function (array $overrides = []): Employee {
        $service = ($this->service)();

        return $service->saveEmployee($service->validateEmployee(['code' => 'E1', 'name' => 'Amina', 'join_date' => $this->month->copy()->subYear()->toDateString(), 'base_salary' => '100000', ...$overrides]));
    };
    $this->balance = fn (string $code): string => (string) DB::table('accounting_journal_entry_lines')->where('chart_of_account_id', account($code)->id)->selectRaw('COALESCE(SUM(base_debit),0) - COALESCE(SUM(base_credit),0) as net')->value('net');
});

it('works out basic pay, allowances and deductions into a net salary', function (): void {
    $hra = ($this->component)();
    $loan = ($this->component)(['code' => 'LOAN', 'name' => 'Staff loan', 'kind' => 'deduction', 'method' => 'fixed', 'value' => 2500, 'account_id' => account('2102')->id]);
    ($this->employee)(['components' => [['pay_component_id' => $hra->id], ['pay_component_id' => $loan->id]]]);

    $run = ($this->service)()->createRun($this->month->toDateString());
    $slip = Payslip::query()->where('payroll_run_id', $run->id)->firstOrFail();

    expect($slip->basic)->toBe('100000.00')->and($slip->gross)->toBe('110000.00')->and($slip->deductions)->toBe('2500.00')->and($slip->tax)->toBe('0.00')->and($slip->net)->toBe('107500.00')
        ->and($run->net)->toBe('107500.00')->and($run->status)->toBe('draft');
});

it('lets an employee override a component value and prorates a new joiner by days', function (): void {
    $hra = ($this->component)();
    ($this->employee)(['join_date' => $this->month->copy()->addDays(14)->toDateString(), 'components' => [['pay_component_id' => $hra->id, 'value' => 20]]]);

    $slip = Payslip::query()->firstWhere('payroll_run_id', ($this->service)()->createRun($this->month->toDateString())->id);
    $days = $this->month->daysInMonth;
    $worked = $days - 14;
    $basic = round(100000 * $worked / $days, 2);

    expect((float) $slip->days_paid)->toBe((float) $worked)->and((float) $slip->basic)->toBe($basic)->and((float) $slip->gross)->toBe(round($basic * 1.2, 2));
});

it('withholds income tax from the configured slabs for flagged employees', function (): void {
    // 250,000 a month → 3,000,000 a year: 116,000 + 23% of 800,000 = 300,000 → 25,000 a month.
    ($this->employee)(['base_salary' => '250000', 'withhold_tax' => true]);
    ($this->employee)(['code' => 'E2', 'name' => 'Not flagged', 'base_salary' => '250000']);
    $run = ($this->service)()->createRun($this->month->toDateString());

    expect(Payslip::query()->orderBy('employee_id')->pluck('tax')->all())->toBe(['25000.00', '0.00'])->and(($this->service)()->annualTax(0))->toBe(0)
        ->and(($this->service)()->annualTax(60000000))->toBe(0)->and(($this->service)()->annualTax(120000000))->toBe(600000)->and($run->tax)->toBe('25000.00');
});

it('posts the run: expense by account, liabilities for deductions and tax, and the net owed', function (): void {
    $loan = ($this->component)(['code' => 'LOAN', 'name' => 'Staff loan', 'kind' => 'deduction', 'method' => 'fixed', 'value' => 1000, 'account_id' => account('2102')->id]);
    ($this->employee)(['base_salary' => '250000', 'withhold_tax' => true, 'components' => [['pay_component_id' => $loan->id]]]);
    $run = ($this->service)()->post(($this->service)()->createRun($this->month->toDateString()));

    expect($run->status)->toBe('posted')->and($run->net)->toBe('224000.00')
        ->and(JournalEntry::query()->findOrFail($run->journal_entry_id)->origin_module)->toBe('payroll');
    expect((float) ($this->balance)('5101'))->toBe(250000.0)->and((float) ($this->balance)('2102'))->toBe(-1000.0)->and((float) ($this->balance)('2104'))->toBe(-25000.0)->and((float) ($this->balance)('2103'))->toBe(-224000.0);
    expect(fn () => ($this->service)()->recalculate($run))->toThrow(AccountingException::class)
        ->and(fn () => ($this->service)()->post($run))->toThrow(AccountingException::class);
});

it('pays the run out of a bank account and clears the net liability', function (): void {
    ($this->employee)();
    $run = ($this->service)()->post(($this->service)()->createRun($this->month->toDateString()));
    $paid = ($this->service)()->pay($run, account('1101')->id, $this->month->copy()->addDays(30)->toDateString());

    expect($paid->status)->toBe('paid')->and((float) ($this->balance)('2103'))->toBe(0.0)->and((float) ($this->balance)('1101'))->toBe(-100000.0)
        ->and(fn () => ($this->service)()->pay($paid, account('1101')->id))->toThrow(AccountingException::class);
});

it('voids a paid run by reversing the payment and the salary entry, and allows the month to be run again', function (): void {
    ($this->employee)();
    $run = ($this->service)()->pay(($this->service)()->post(($this->service)()->createRun($this->month->toDateString())), account('1101')->id, $this->month->copy()->addDays(30)->toDateString());
    $void = ($this->service)()->void($run);

    expect($void->status)->toBe('void')->and((float) ($this->balance)('5101'))->toBe(0.0)->and((float) ($this->balance)('1101'))->toBe(0.0)->and((float) ($this->balance)('2103'))->toBe(0.0);
    expect(($this->service)()->createRun($this->month->toDateString())->status)->toBe('draft');
});

it('refuses a second run for a month, a negative net salary and a closed period', function (): void {
    $loan = ($this->component)(['code' => 'BIG', 'name' => 'Big deduction', 'kind' => 'deduction', 'method' => 'fixed', 'value' => 200000, 'account_id' => account('2102')->id]);
    ($this->employee)();
    ($this->service)()->createRun($this->month->toDateString());
    expect(fn () => ($this->service)()->createRun($this->month->toDateString()))->toThrow(ValidationException::class);

    ($this->service)()->deleteRun(PayrollRun::query()->firstOrFail());
    ($this->employee)(['code' => 'E9', 'name' => 'Overdrawn', 'components' => [['pay_component_id' => $loan->id]]]);
    expect(fn () => ($this->service)()->createRun($this->month->toDateString()))->toThrow(AccountingException::class, 'negative');
});

it('stops posting in a closed period and leaves the run a draft', function (): void {
    ($this->employee)();
    $run = ($this->service)()->createRun($this->month->toDateString());
    AccountingPeriod::query()->whereDate('start_date', '<=', $this->month->toDateString())->whereDate('end_date', '>=', $this->month->toDateString())->update(['status' => 'closed']);

    expect(fn () => ($this->service)()->post($run))->toThrow(Exception::class);
    expect($run->refresh()->status)->toBe('draft')->and(JournalEntry::query()->count())->toBe(0);
});

it('validates components by account type and protects used records', function (): void {
    expect(fn () => ($this->component)(['account_id' => account('2102')->id]))->toThrow(ValidationException::class)
        ->and(fn () => ($this->component)(['kind' => 'deduction', 'account_id' => account('5102')->id]))->toThrow(ValidationException::class);
    $hra = ($this->component)();
    $employee = ($this->employee)(['components' => [['pay_component_id' => $hra->id]]]);
    ($this->service)()->createRun($this->month->toDateString());

    expect(fn () => ($this->service)()->deleteComponent($hra))->toThrow(AccountingException::class)
        ->and(fn () => ($this->service)()->deleteEmployee($employee))->toThrow(AccountingException::class);
});

it('serves the pages and the API', function (): void {
    $hra = ($this->component)();
    $employee = ($this->employee)(['components' => [['pay_component_id' => $hra->id]]]);

    $this->get('/accounting/payroll')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/index'));
    $this->get('/accounting/payroll/employees')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/employees')->has('employees', 1));
    $this->get('/accounting/payroll/employees/create')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/employee-form'));
    $this->get('/accounting/payroll/components')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/components'));
    $this->post('/accounting/payroll/runs', ['period_month' => $this->month->toDateString()])->assertRedirect();
    $run = PayrollRun::query()->firstOrFail();
    $this->get("/accounting/payroll/runs/{$run->id}")->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/run-show')->where('run.net', '110000.00'));
    $slip = Payslip::query()->firstOrFail();
    $this->get("/accounting/payroll/runs/{$run->id}/payslips/{$slip->id}")->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/payslip')->where('payslip.net', '110000.00'));

    Sanctum::actingAs($this->accountant);
    $this->getJson('/api/v1/accounting/payroll/employees')->assertOk()->assertJsonPath('data.0.code', 'E1');
    $this->postJson("/api/v1/accounting/payroll/runs/{$run->id}/post")->assertForbidden();   // posting is the approver's
    $approver = User::factory()->create();
    $approver->assignRole('approver');
    Sanctum::actingAs($approver);
    $this->postJson("/api/v1/accounting/payroll/runs/{$run->id}/post")->assertOk()->assertJsonPath('data.status', 'posted');
    $this->postJson("/api/v1/accounting/payroll/runs/{$run->id}/pay", ['account_id' => account('1101')->id, 'date' => $this->month->copy()->addDays(30)->toDateString()])->assertOk()->assertJsonPath('data.status', 'paid');
    $this->postJson("/api/v1/accounting/payroll/runs/{$run->id}/void")->assertOk()->assertJsonPath('data.status', 'void');
    expect($employee->refresh()->code)->toBe('E1');
});
