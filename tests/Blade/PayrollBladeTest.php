<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('sets up staff, runs payroll, posts, pays and voids it in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $approver = User::factory()->create();
    $approver->assignRole('approver');
    $month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();

    $this->actingAs($accountant);
    $this->get('/accounting')->assertSee('Payroll');
    $this->post('/accounting/payroll/components', ['code' => 'HRA', 'name' => 'House rent', 'kind' => 'earning', 'method' => 'percent_of_basic', 'value' => 10, 'taxable' => 1, 'account_id' => account('5102')->id])->assertRedirect();
    $this->get('/accounting/payroll/components')->assertOk()->assertSee('House rent');
    $component = PayComponent::query()->firstOrFail();
    $this->get('/accounting/payroll/employees/create')->assertOk()->assertSee('House rent')->assertSee('Withhold income tax');
    $this->post('/accounting/payroll/employees', ['code' => 'E1', 'name' => 'Amina Khan', 'join_date' => $month->copy()->subYear()->toDateString(), 'base_salary' => '80000', 'withhold_tax' => 0, 'components' => [['pay_component_id' => $component->id, 'value' => '']]])->assertRedirect();
    expect(Employee::query()->count())->toBe(1);
    $this->get('/accounting/payroll/employees')->assertOk()->assertSee('Amina Khan')->assertSee('80,000.00');

    $this->post('/accounting/payroll/runs', ['period_month' => $month->toDateString()])->assertRedirect();
    $run = PayrollRun::query()->firstOrFail();
    $this->get("/accounting/payroll/runs/{$run->id}")->assertOk()->assertSee('88,000.00')->assertSee('Amina Khan');
    $slip = Payslip::query()->firstOrFail();
    $this->get("/accounting/payroll/runs/{$run->id}/payslips/{$slip->id}")->assertOk()->assertSee('House rent')->assertSee('Net pay');
    $this->post("/accounting/payroll/runs/{$run->id}/post")->assertForbidden();

    $this->actingAs($approver);
    $this->post("/accounting/payroll/runs/{$run->id}/post")->assertRedirect()->assertSessionHas('success');
    $this->post("/accounting/payroll/runs/{$run->id}/pay", ['account_id' => account('1101')->id, 'date' => $month->copy()->addDays(30)->toDateString()])->assertRedirect()->assertSessionHas('success');
    $this->get("/accounting/payroll/runs/{$run->id}")->assertSee('paid');
    $this->post("/accounting/payroll/runs/{$run->id}/void")->assertRedirect()->assertSessionHas('success');
    expect($run->refresh()->status)->toBe('void');
});
