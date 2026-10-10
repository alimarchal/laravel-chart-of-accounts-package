<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollAdjustment;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollAdjustmentService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->hr = tap(User::factory()->create())->assignRole('accountant');
    $this->finance = tap(User::factory()->create())->assignRole('approver');
    $this->actingAs($this->hr);
    $this->month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->payroll = fn () => app(PayrollService::class);
    $this->adjustments = fn () => app(PayrollAdjustmentService::class);
    $this->employee = function (array $overrides = []): Employee {
        $service = ($this->payroll)();

        return $service->saveEmployee($service->validateEmployee(['code' => 'E1', 'name' => 'Amina', 'join_date' => $this->month->copy()->subYear()->toDateString(), 'base_salary' => '100000', ...$overrides]));
    };
    $this->slip = fn (Employee $employee, PayrollRun $run) => Payslip::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->firstOrFail();
});

it('posts a draft straight away while approval is off', function (): void {
    ($this->employee)();
    $run = ($this->payroll)()->createRun($this->month->toDateString());
    $this->actingAs($this->finance);

    expect(($this->payroll)()->approvalRequired())->toBeFalse()->and(($this->payroll)()->post($run)->status)->toBe('posted');
});

it('needs HR to submit and finance to approve before a run is posted', function (): void {
    ($this->features = app(FeatureManager::class))->set('payroll_approval', true);
    ($this->employee)();
    $payroll = ($this->payroll)();
    $run = $payroll->createRun($this->month->toDateString());

    $this->actingAs($this->finance);
    expect(fn () => $payroll->post($run))->toThrow(AccountingException::class, 'submitted and approved');

    $this->actingAs($this->hr);
    expect($payroll->submit($run)->status)->toBe('submitted')
        ->and(fn () => $payroll->recalculate($run))->toThrow(AccountingException::class)
        ->and(fn () => $payroll->deleteRun($run))->toThrow(AccountingException::class)
        ->and(fn () => $payroll->approve($run))->toThrow(AccountingException::class, 'someone else');

    $this->actingAs($this->finance);
    expect(fn () => $payroll->post($run))->toThrow(AccountingException::class, 'approved run');
    $approved = $payroll->approve($run);
    expect($approved->status)->toBe('approved')->and($approved->approved_by)->toBe($this->finance->id)->and($approved->submitted_by)->toBe($this->hr->id);
    expect($payroll->post($run)->status)->toBe('posted');
    expect(AccountingAuditLog::query()->whereIn('action', ['PAYROLL_RUN_SUBMITTED', 'PAYROLL_RUN_APPROVED'])->count())->toBe(2);
});

it('sends a run back with the reason, or takes it back, and lets HR change it', function (): void {
    app(FeatureManager::class)->set('payroll_approval', true);
    ($this->employee)();
    $payroll = ($this->payroll)();
    $run = $payroll->createRun($this->month->toDateString());
    $payroll->submit($run);

    $this->actingAs($this->finance);
    expect(fn () => $payroll->reject($run, '  '))->toThrow(ValidationException::class);
    $back = $payroll->reject($run, 'Overtime of E1 looks wrong');
    expect($back->status)->toBe('draft')->and($back->rejection_reason)->toBe('Overtime of E1 looks wrong')->and($back->submitted_by)->toBeNull();

    $this->actingAs($this->hr);
    $payroll->recalculate($run);
    $payroll->submit($run);
    expect($payroll->withdraw($run)->status)->toBe('draft')->and(fn () => $payroll->withdraw($run))->toThrow(AccountingException::class);
});

it('adds a bonus and a fine to the payslip of the month, taxed or not as chosen', function (): void {
    $employee = ($this->employee)(['withhold_tax' => false]);
    $fine = PayComponent::query()->create(($this->payroll)()->validateComponent(['code' => 'FINE', 'name' => 'Fine', 'kind' => 'deduction', 'method' => 'fixed', 'value' => 0, 'account_id' => account('2102')->id]));
    $adjustments = ($this->adjustments)();
    $adjustments->create($adjustments->validate(['employee_id' => $employee->id, 'month' => $this->month->toDateString(), 'description' => 'Eid bonus', 'amount' => 25000]));
    $adjustments->create($adjustments->validate(['employee_id' => $employee->id, 'month' => $this->month->copy()->addDays(10)->toDateString(), 'description' => 'Late fine', 'amount' => 1500, 'pay_component_id' => $fine->id]));
    $adjustments->create($adjustments->validate(['employee_id' => $employee->id, 'month' => $this->month->copy()->addMonth()->toDateString(), 'description' => 'Next month', 'amount' => 999]));

    $run = ($this->payroll)()->createRun($this->month->toDateString());
    $slip = ($this->slip)($employee, $run);
    $lines = PayslipLine::query()->where('payslip_id', $slip->id)->get()->keyBy('description');

    expect($lines['Eid bonus']->kind)->toBe('earning')->and($lines['Eid bonus']->amount)->toBe('25000.00')
        ->and($lines['Late fine']->kind)->toBe('deduction')
        ->and((float) $slip->gross)->toBe(125000.0)->and((float) $slip->deductions)->toBe(1500.0)->and((float) $slip->net)->toBe(123500.0);
    expect(PayrollAdjustment::query()->where('status', 'included')->count())->toBe(2)->and(PayrollAdjustment::query()->where('status', 'open')->count())->toBe(1);
});

it('gives the adjustments back when the run is recalculated, deleted or voided', function (): void {
    $employee = ($this->employee)();
    $adjustments = ($this->adjustments)();
    $adjustments->create($adjustments->validate(['employee_id' => $employee->id, 'month' => $this->month->toDateString(), 'description' => 'Bonus', 'amount' => 5000]));
    $payroll = ($this->payroll)();
    $run = $payroll->createRun($this->month->toDateString());

    $payroll->recalculate($run);
    expect(PayrollAdjustment::query()->firstOrFail()->status)->toBe('included')->and(PayslipLine::query()->where('description', 'Bonus')->count())->toBe(1);
    expect(fn () => $adjustments->cancel(PayrollAdjustment::query()->firstOrFail()))->toThrow(AccountingException::class, 'already includes');

    $payroll->deleteRun($run);
    expect(PayrollAdjustment::query()->firstOrFail()->status)->toBe('open');

    $run = $payroll->createRun($this->month->toDateString());
    $this->actingAs($this->finance);
    $payroll->post($run);
    expect(fn () => $adjustments->create($adjustments->validate(['employee_id' => $employee->id, 'month' => $this->month->toDateString(), 'description' => 'Late', 'amount' => 1])))->toThrow(AccountingException::class, 'already past the draft stage');
    $payroll->void($run);
    expect(PayrollAdjustment::query()->firstOrFail()->status)->toBe('open');
    expect($adjustments->cancel(PayrollAdjustment::query()->firstOrFail())->status)->toBe('cancelled');
});

it('gives a bonus to many employees at once: a fixed amount or a percent of each basic', function (): void {
    ($this->employee)();
    ($this->employee)(['code' => 'E2', 'name' => 'Bilal', 'base_salary' => '60000']);
    ($this->employee)(['code' => 'E3', 'name' => 'Newcomer', 'join_date' => $this->month->copy()->addMonths(3)->toDateString()]);
    $adjustments = ($this->adjustments)();

    $result = $adjustments->createBulk($adjustments->validateBulk(['month' => $this->month->toDateString(), 'description' => 'Festival bonus', 'method' => 'percent_of_basic', 'value' => 50]));
    expect($result['created'])->toBe(2)->and($result['total'])->toBe('80000.00');
    expect(PayrollAdjustment::query()->orderBy('id')->pluck('amount')->all())->toBe(['50000.00', '30000.00']);

    $fixed = $adjustments->createBulk($adjustments->validateBulk(['month' => $this->month->toDateString(), 'description' => 'Allowance', 'method' => 'fixed', 'value' => 2000, 'employee_ids' => [Employee::query()->where('code', 'E2')->value('id')]]));
    expect($fixed['created'])->toBe(1)->and(fn () => $adjustments->validateBulk(['month' => $this->month->toDateString(), 'description' => 'x', 'method' => 'percent_of_basic', 'value' => 5000]))->toThrow(ValidationException::class);
});

it('needs an account for a deduction without a component and rejects bad input', function (): void {
    $employee = ($this->employee)();
    $adjustments = ($this->adjustments)();

    expect(fn () => $adjustments->validate(['employee_id' => $employee->id, 'month' => $this->month->toDateString(), 'description' => 'Fine', 'amount' => 10, 'kind' => 'deduction']))->toThrow(ValidationException::class)
        ->and(fn () => $adjustments->validate(['employee_id' => $employee->id, 'month' => $this->month->toDateString(), 'description' => 'Zero', 'amount' => 0]))->toThrow(ValidationException::class);
    expect($adjustments->validate(['employee_id' => $employee->id, 'month' => $this->month->toDateString(), 'description' => 'Fine', 'amount' => 10, 'kind' => 'deduction', 'account_id' => account('2102')->id])['taxable'])->toBeFalse();
});

it('leaves one-off pay out of the run while the feature is off', function (): void {
    $employee = ($this->employee)();
    $adjustments = ($this->adjustments)();
    $adjustments->create($adjustments->validate(['employee_id' => $employee->id, 'month' => $this->month->toDateString(), 'description' => 'Bonus', 'amount' => 5000]));

    app(FeatureManager::class)->set('payroll_adjustments', false);
    $run = ($this->payroll)()->createRun($this->month->toDateString());
    expect(PayslipLine::query()->where('description', 'Bonus')->count())->toBe(0)->and((float) $run->gross)->toBe(100000.0);
});

it('serves approval and adjustments over the API with the right permissions', function (): void {
    app(FeatureManager::class)->set('payroll_approval', true);
    $employee = ($this->employee)();

    Sanctum::actingAs($this->hr);
    $this->postJson('/api/v1/accounting/payroll/adjustments', ['employee_id' => $employee->id, 'month' => $this->month->toDateString(), 'description' => 'Bonus', 'amount' => 5000])->assertCreated();
    $this->postJson('/api/v1/accounting/payroll/adjustments/bulk', ['month' => $this->month->toDateString(), 'description' => 'Allowance', 'method' => 'fixed', 'value' => 100])->assertCreated()->assertJsonPath('data.created', 1);
    $this->getJson('/api/v1/accounting/payroll/adjustments?month='.$this->month->format('Y-m'))->assertOk()->assertJsonCount(2, 'data');

    $runId = $this->postJson('/api/v1/accounting/payroll/runs', ['period_month' => $this->month->toDateString()])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/accounting/payroll/runs/{$runId}/approve")->assertForbidden();
    $this->postJson("/api/v1/accounting/payroll/runs/{$runId}/post")->assertForbidden();
    $this->postJson("/api/v1/accounting/payroll/runs/{$runId}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

    Sanctum::actingAs($this->finance);
    $this->postJson("/api/v1/accounting/payroll/runs/{$runId}/reject", [])->assertUnprocessable();
    $this->postJson("/api/v1/accounting/payroll/runs/{$runId}/reject", ['reason' => 'Check the bonus'])->assertOk()->assertJsonPath('data.status', 'draft');
    $this->getJson("/api/v1/accounting/payroll/runs/{$runId}")->assertOk()->assertJsonPath('data.run.rejection_reason', 'Check the bonus')->assertJsonPath('data.run.approval_required', true);

    Sanctum::actingAs($this->hr);
    $this->postJson("/api/v1/accounting/payroll/runs/{$runId}/submit")->assertOk();
    Sanctum::actingAs($this->finance);
    $this->postJson("/api/v1/accounting/payroll/runs/{$runId}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    $this->postJson("/api/v1/accounting/payroll/runs/{$runId}/post")->assertOk();
});
