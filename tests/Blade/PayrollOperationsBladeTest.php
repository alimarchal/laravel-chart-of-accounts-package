<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ContributionScheme;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\Loan;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('runs attendance, leave, loans, contributions and the bank file in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $approver = User::factory()->create();
    $approver->assignRole('approver');
    $month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();

    $this->actingAs($accountant);
    $this->post('/accounting/payroll/employees', ['code' => 'E1', 'name' => 'Amina Khan', 'join_date' => $month->copy()->subYear()->toDateString(), 'base_salary' => '100000', 'overtime_eligible' => 1, 'bank_name' => 'HBL', 'bank_account' => 'PK36HABB0000000123456702'])->assertRedirect();
    $employee = Employee::query()->firstOrFail();
    expect($employee->overtime_eligible)->toBeTrue();
    $this->get("/accounting/payroll/employees/{$employee->id}/edit")->assertOk()->assertSee('Paid overtime');

    $this->get('/accounting/payroll')->assertSee('Attendance')->assertSee('Loans')->assertSee('Contributions');
    $this->post('/accounting/payroll/leave-types', ['code' => 'ANN', 'name' => 'Annual leave', 'is_paid' => 1, 'annual_days' => 20])->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting/payroll/leaves')->assertOk()->assertSee('Annual leave')->assertSee('Record leave');
    $this->post('/accounting/payroll/leaves', ['employee_id' => $employee->id, 'leave_type_id' => 1, 'from_date' => $month->toDateString(), 'to_date' => $month->copy()->addDays(2)->toDateString()])->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting/payroll/leaves')->assertSee('Amina Khan')->assertSee('17');

    $this->post('/accounting/payroll/attendance', ['month' => $month->toDateString(), 'rows' => [['employee_id' => $employee->id, 'absent_days' => 1, 'overtime_hours' => 8]]])->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting/payroll/attendance?month='.$month->format('Y-m'))->assertOk()->assertSee('Amina Khan')->assertSee('Save attendance');

    $this->post('/accounting/payroll/schemes', ['code' => 'EOBI', 'name' => 'EOBI', 'base' => 'basic', 'employee_rate' => 1, 'employer_rate' => 5, 'ceiling' => 30000, 'employee_account_id' => account('2102')->id, 'employer_expense_account_id' => account('5102')->id, 'employer_liability_account_id' => account('2102')->id, 'applies_to_all' => 1])->assertRedirect()->assertSessionHas('success');
    $scheme = ContributionScheme::query()->firstOrFail();
    $this->get('/accounting/payroll/schemes')->assertOk()->assertSee('EOBI')->assertSee('everybody');
    $this->get("/accounting/payroll/schemes?edit={$scheme->id}")->assertSee('Edit EOBI');
    $this->post("/accounting/payroll/schemes/{$scheme->id}/assign", ['mode' => 'assign'])->assertRedirect()->assertSessionHas('success');

    $this->post('/accounting/payroll/loans', ['employee_id' => $employee->id, 'kind' => 'advance', 'principal' => 9000, 'installments' => 3, 'start_month' => $month->toDateString()])->assertRedirect()->assertSessionHas('success');
    $loan = Loan::query()->firstOrFail();
    $this->get('/accounting/payroll/loans')->assertOk()->assertSee('Amina Khan')->assertSee('9,000.00')->assertSee('draft');
    $this->post("/accounting/payroll/loans/{$loan->id}/disburse", ['account_id' => account('1101')->id])->assertForbidden();
    $this->actingAs($approver)->post("/accounting/payroll/loans/{$loan->id}/disburse", ['account_id' => account('1101')->id, 'date' => $month->toDateString()])->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting/payroll/loans?status=active')->assertSee('active')->assertSee('Settle');

    $this->actingAs($accountant)->post('/accounting/payroll/runs', ['period_month' => $month->toDateString()])->assertRedirect();
    $run = PayrollRun::query()->firstOrFail();
    $slip = Payslip::query()->firstOrFail();
    $this->get("/accounting/payroll/runs/{$run->id}/payslips/{$slip->id}")->assertOk()->assertSee('Overtime (8 h)')->assertSee('Advance recovery')->assertSee('Employer contributions')->assertSee('EOBI (employer)');
    $this->actingAs($approver)->post("/accounting/payroll/runs/{$run->id}/post")->assertRedirect()->assertSessionHas('success');
    $this->get("/accounting/payroll/runs/{$run->id}")->assertOk()->assertSee('Bank salary file')->assertSee('standard');
    $csv = $this->get("/accounting/payroll/runs/{$run->id}/bank-file/csv")->assertOk()->streamedContent();
    expect($csv)->toContain('Amina Khan')->toContain('PK36HABB0000000123456702');
    $this->actingAs($accountant)->get("/accounting/payroll/runs/{$run->id}/bank-file/csv")->assertForbidden();
});
