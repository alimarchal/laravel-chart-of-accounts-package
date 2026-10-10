<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollAdjustment;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('submits, approves and posts a run, and adds bonuses, in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $hr = User::factory()->create();
    $hr->assignRole('accountant');
    $finance = User::factory()->create();
    $finance->assignRole('approver');
    $month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    app(FeatureManager::class)->set('payroll_approval', true);

    $this->actingAs($hr);
    $this->post('/accounting/payroll/employees', ['code' => 'E1', 'name' => 'Amina Khan', 'join_date' => $month->copy()->subYear()->toDateString(), 'base_salary' => '90000'])->assertRedirect();
    $employee = Employee::query()->firstOrFail();

    $this->get('/accounting/payroll/adjustments?month='.$month->format('Y-m'))->assertOk()->assertSee('Bonuses and one-off pay')->assertSee('Every active employee')->assertSee('Nothing for');
    $this->post('/accounting/payroll/adjustments', ['employee_id' => $employee->id, 'month' => $month->toDateString(), 'description' => 'Eid bonus', 'amount' => 10000, 'taxable' => 1])->assertRedirect()->assertSessionHas('success');
    $this->post('/accounting/payroll/adjustments/bulk', ['month' => $month->toDateString(), 'description' => 'Allowance', 'method' => 'percent_of_basic', 'value' => 10])->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting/payroll/adjustments?month='.$month->format('Y-m'))->assertSee('Eid bonus')->assertSee('10,000.00')->assertSee('9,000.00');

    $this->post('/accounting/payroll/runs', ['period_month' => $month->toDateString()])->assertRedirect();
    $run = PayrollRun::query()->firstOrFail();
    $this->get("/accounting/payroll/runs/{$run->id}")->assertOk()->assertSee('Submit for approval')->assertDontSee('>Post<', false);
    $this->post("/accounting/payroll/runs/{$run->id}/post")->assertForbidden();
    $this->post("/accounting/payroll/runs/{$run->id}/submit")->assertRedirect()->assertSessionHas('success');
    $this->get("/accounting/payroll/runs/{$run->id}")->assertSee('Withdraw')->assertDontSee('Recalculate');
    $this->post('/accounting/payroll/adjustments/'.PayrollAdjustment::query()->firstOrFail()->id.'/cancel')->assertRedirect()->assertSessionHas('error');

    $this->actingAs($finance);
    $this->get("/accounting/payroll/runs/{$run->id}")->assertOk()->assertSee('Approve')->assertSee('Send back')->assertSee('Submitted by');
    $this->post("/accounting/payroll/runs/{$run->id}/reject", ['reason' => 'Check the bonus'])->assertRedirect()->assertSessionHas('success');
    $this->get("/accounting/payroll/runs/{$run->id}")->assertSee('Sent back by finance: Check the bonus');

    $this->actingAs($hr)->post("/accounting/payroll/runs/{$run->id}/submit")->assertRedirect();
    $this->actingAs($finance)->post("/accounting/payroll/runs/{$run->id}/approve")->assertRedirect()->assertSessionHas('success');
    $this->get("/accounting/payroll/runs/{$run->id}")->assertSee('Approved by')->assertSee('Post');
    $this->post("/accounting/payroll/runs/{$run->id}/post")->assertRedirect()->assertSessionHas('success');
    expect($run->refresh()->status)->toBe('posted');
});
