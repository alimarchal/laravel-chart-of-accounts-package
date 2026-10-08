<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollArrear;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGrade;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('manages grades, bulk changes, raises and arrears in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $approver = User::factory()->create();
    $approver->assignRole('approver');
    $month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();

    $this->actingAs($accountant);
    $this->post('/accounting/payroll/components', ['code' => 'FUEL', 'name' => 'Fuel allowance', 'kind' => 'earning', 'method' => 'quantity_rate', 'value' => 75, 'rate' => 280, 'unit' => 'litre', 'taxable' => 1, 'account_id' => account('5102')->id])->assertRedirect();
    $this->get('/accounting/payroll/components')->assertOk()->assertSee('75 litre x 280')->assertSee('Quantity x rate');
    $fuel = PayComponent::query()->firstOrFail();

    $this->post('/accounting/payroll/grades', ['code' => 'G1', 'name' => 'Officer', 'base_salary' => 80000, 'is_active' => 1, 'components' => [['pay_component_id' => $fuel->id, 'value' => '']]])->assertRedirect();
    $grade = SalaryGrade::query()->firstOrFail();
    $this->get('/accounting/payroll/grades')->assertOk()->assertSee('Officer')->assertSee('Fuel allowance')->assertSee('80,000.00');
    $this->get("/accounting/payroll/grades?edit={$grade->id}")->assertOk()->assertSee('Edit G1');

    $this->post('/accounting/payroll/employees', ['code' => 'E1', 'name' => 'Amina Khan', 'join_date' => $month->copy()->subYear()->toDateString(), 'salary_grade_id' => $grade->id, 'base_salary' => '', 'withhold_tax' => 0])->assertRedirect();
    $employee = Employee::query()->firstOrFail();
    expect($employee->base_salary)->toBe('80000.00');
    $this->get("/accounting/payroll/employees/{$employee->id}/edit")->assertOk()->assertSee('Salary grade')->assertSee('New salary applies from');
    $this->get('/accounting/payroll/bulk')->assertOk()->assertSee('Raise salaries')->assertSee('Amina Khan')->assertSee('Put employees on a grade');
    $this->post('/accounting/payroll/bulk/components', ['pay_component_id' => $fuel->id, 'mode' => 'assign', 'value' => 100])->assertRedirect()->assertSessionHas('success');

    $this->post('/accounting/payroll/runs', ['period_month' => $month->toDateString()])->assertRedirect();
    $run = PayrollRun::query()->firstOrFail();
    $this->actingAs($approver)->post("/accounting/payroll/runs/{$run->id}/post")->assertRedirect()->assertSessionHas('success');

    $this->actingAs($accountant);
    $this->from('/accounting/payroll/bulk')->post('/accounting/payroll/revisions/preview', ['mode' => 'percent', 'value' => 10, 'effective_from' => $month->toDateString()])->assertRedirect('/accounting/payroll/bulk');
    $this->withSession(['revision_preview' => [['employee_id' => 1, 'code' => 'E1', 'name' => 'Amina Khan', 'old_salary' => '80000.00', 'new_salary' => '88000.00', 'difference' => '8000.00']]])->get('/accounting/payroll/bulk')->assertSee('88,000.00')->assertSee('Apply');
    $this->post('/accounting/payroll/revisions', ['mode' => 'percent', 'value' => 10, 'effective_from' => $month->toDateString()])->assertRedirect()->assertSessionHas('success');
    expect($employee->refresh()->base_salary)->toBe('88000.00');

    $this->post('/accounting/payroll/arrears', ['from_month' => $month->toDateString(), 'payment_month' => $month->copy()->addMonth()->toDateString()])->assertRedirect()->assertSessionHas('success');
    $arrear = PayrollArrear::query()->firstOrFail();
    $this->get('/accounting/payroll/arrears')->assertOk()->assertSee('Register')->assertSee('Amina Khan')->assertSee('draft');
    $this->post("/accounting/payroll/arrears/{$arrear->id}/approve")->assertForbidden();
    $this->actingAs($approver)->post("/accounting/payroll/arrears/{$arrear->id}/approve")->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting/payroll/arrears')->assertSee('approved');
    $this->get('/accounting/payroll')->assertSee('Arrears')->assertSee('Grades');

    $this->actingAs($accountant)->post('/accounting/payroll/runs', ['period_month' => $month->copy()->addMonth()->toDateString()])->assertRedirect();
    $next = PayrollRun::query()->orderByDesc('id')->firstOrFail();
    $slip = Payslip::query()->where('payroll_run_id', $next->id)->firstOrFail();
    $this->get("/accounting/payroll/runs/{$next->id}/payslips/{$slip->id}")->assertOk()->assertSee('Arrears (')->assertSee('8,000.00');
});
