<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\Settlement;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('prints payslips, settles an employee, and shows the reports and tax screens in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $approver = User::factory()->create();
    $approver->assignRole('approver');
    $month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();

    $this->actingAs($accountant);
    $this->post('/accounting/payroll/employees', ['code' => 'E1', 'name' => 'Amina Khan', 'email' => 'amina@example.com', 'join_date' => $month->copy()->subYears(3)->toDateString(), 'base_salary' => '90000'])->assertRedirect()->assertSessionHasNoErrors();
    $employee = Employee::query()->firstOrFail();
    expect($employee->email)->toBe('amina@example.com');
    $this->get("/accounting/payroll/employees/{$employee->id}/edit")->assertOk()->assertSee('amina@example.com')->assertSee('payslips are sent here');
    $this->get('/accounting/payroll')->assertSee('Settlements')->assertSee('Reports')->assertSee('Tax');

    $this->post('/accounting/payroll/runs', ['period_month' => $month->toDateString()])->assertRedirect();
    $run = PayrollRun::query()->firstOrFail();
    $slip = Payslip::query()->firstOrFail();
    $this->actingAs($approver)->post("/accounting/payroll/runs/{$run->id}/post")->assertRedirect()->assertSessionHas('success');
    $this->actingAs($accountant)->get("/accounting/payroll/runs/{$run->id}")->assertSee('E-mail payslips');
    $this->get("/accounting/payroll/runs/{$run->id}/payslips/{$slip->id}")->assertOk()->assertSee('PDF')->assertSee('E-mail');
    $this->get("/accounting/payroll/runs/{$run->id}/payslips/{$slip->id}/print")->assertOk()->assertSee('Amina Khan');
    $this->get("/accounting/payroll/runs/{$run->id}/payslips/{$slip->id}/pdf")->assertOk();

    $this->get('/accounting/payroll/settlements')->assertOk()->assertSee('Final settlements')->assertSee('No settlements yet.');
    $leave = $month->copy()->addMonths(1)->toDateString();
    $this->post('/accounting/payroll/settlements/preview', ['employee_id' => $employee->id, 'leave_date' => $leave])->assertRedirect()->assertSessionHas('settlement_preview');
    $this->get('/accounting/payroll/settlements')->assertOk();
    $this->post('/accounting/payroll/settlements', ['employee_id' => $employee->id, 'leave_date' => $leave, 'gratuity' => 50000])->assertRedirect()->assertSessionHas('success');
    $settlement = Settlement::query()->firstOrFail();
    $this->get('/accounting/payroll/settlements')->assertSee('Amina Khan')->assertSee('50,000.00')->assertSee('draft');
    $this->post("/accounting/payroll/settlements/{$settlement->id}/post")->assertForbidden();
    $this->actingAs($approver)->post("/accounting/payroll/settlements/{$settlement->id}/post")->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting/payroll/settlements')->assertSee('posted')->assertSee('Paid from');

    $this->get('/accounting/payroll/reports?year='.$month->year)->assertOk()->assertSee('Month by month')->assertSee('Cost centers')->assertSee('Headcount');
    $this->get('/accounting/payroll/reports/comparison/export/csv?year='.$month->year)->assertOk();
    $this->get('/accounting/payroll/tax?year='.$month->year)->assertOk()->assertSee('Salary tax statement')->assertSee('Open certificate');
    $this->get("/accounting/payroll/tax/certificate/{$employee->id}?year=".$month->year)->assertOk()->assertSee('Salary tax certificate')->assertSee('Amina Khan');
});

it('hides a switched-off feature from the menus and shows the switches screen in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $this->actingAs($admin);

    $this->get('/accounting')->assertOk()->assertSee('Inventory')->assertSee('Features');
    $this->get('/settings/features')->assertOk()->assertSee('Loans &amp; advances', false)->assertSee('Final settlements')->assertSee('Payroll');

    $this->put('/settings/features', ['features' => ['inventory' => '0', 'payroll_loans' => '0']])->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting')->assertOk()->assertDontSee('Items, warehouses, stock movements');
    $this->get('/accounting/inventory')->assertNotFound();
    $this->get('/accounting/payroll')->assertOk()->assertDontSee('/accounting/payroll/loans');
    $this->get('/accounting/payroll/loans')->assertNotFound();
    $this->get('/settings/features')->assertOk();
});
