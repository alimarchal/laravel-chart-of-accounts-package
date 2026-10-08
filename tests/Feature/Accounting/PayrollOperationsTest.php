<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Attendance;
use Alimarchal\LaravelChartOfAccounts\Models\ContributionScheme;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\LeaveType;
use Alimarchal\LaravelChartOfAccounts\Models\Loan;
use Alimarchal\LaravelChartOfAccounts\Models\LoanInstallment;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollArrearsService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollAttendanceService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollContributionService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollLoanService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollStructureService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->actingAs(tap(User::factory()->create())->assignRole('accountant'));
    $this->month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->payroll = fn () => app(PayrollService::class);
    $this->attendance = fn () => app(PayrollAttendanceService::class);
    $this->loans = fn () => app(PayrollLoanService::class);
    $this->schemes = fn () => app(PayrollContributionService::class);
    $this->employee = function (array $overrides = []): Employee {
        $service = ($this->payroll)();

        return $service->saveEmployee($service->validateEmployee(['code' => 'E1', 'name' => 'Amina', 'join_date' => $this->month->copy()->subYear()->toDateString(), 'base_salary' => '100000', ...$overrides]));
    };
    $this->slip = fn (Employee $employee, $run) => Payslip::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->firstOrFail();
    $this->run = fn (int $offset = 0) => ($this->payroll)()->createRun($this->month->copy()->addMonths($offset)->toDateString());
    $this->balance = fn (string $code): float => (float) DB::table('accounting_journal_entry_lines')->where('chart_of_account_id', account($code)->id)->selectRaw('COALESCE(SUM(base_debit),0) - COALESCE(SUM(base_credit),0) as net')->value('net');
    $this->sheet = fn (int $offset, array $rows) => ($this->attendance)()->saveSheet(($this->attendance)()->validateSheet(['month' => $this->month->copy()->addMonths($offset)->toDateString(), 'rows' => $rows]));
    $this->leaveType = fn (array $overrides = []): LeaveType => LeaveType::query()->create(($this->attendance)()->validateLeaveType(['code' => 'ANN', 'name' => 'Annual', 'is_paid' => true, 'annual_days' => 20, ...$overrides]));
});

it('takes absent days off the basic and the fixed allowances, and the percent allowances follow the basic', function (): void {
    $hra = PayComponent::query()->create(($this->payroll)()->validateComponent(['code' => 'HRA', 'name' => 'House rent', 'kind' => 'earning', 'method' => 'percent_of_basic', 'value' => 10, 'account_id' => account('5102')->id]));
    $conv = PayComponent::query()->create(($this->payroll)()->validateComponent(['code' => 'CONV', 'name' => 'Conveyance', 'kind' => 'earning', 'method' => 'fixed', 'value' => 3100, 'account_id' => account('5102')->id]));
    $employee = ($this->employee)(['components' => [['pay_component_id' => $hra->id], ['pay_component_id' => $conv->id]]]);
    ($this->sheet)(0, [['employee_id' => $employee->id, 'absent_days' => 3]]);

    $slip = ($this->slip)($employee, ($this->run)());
    $days = $this->month->daysInMonth;
    $basic = round(100000 * ($days - 3) / $days, 2);

    expect((float) $slip->basic)->toBe($basic)->and((float) $slip->days_paid)->toBe((float) ($days - 3))
        ->and((float) $slip->gross)->toBe(round($basic + $basic / 10 + 3100 * ($days - 3) / $days, 2));
});

it('counts unpaid leave like absence, not paid leave, and keeps balances', function (): void {
    $employee = ($this->employee)();
    $annual = ($this->leaveType)();
    $unpaid = ($this->leaveType)(['code' => 'UNP', 'name' => 'Unpaid', 'is_paid' => false, 'annual_days' => 0]);
    $service = ($this->attendance)();
    $from = $this->month->copy()->addDays(1);

    $service->createLeave($service->validateLeave(['employee_id' => $employee->id, 'leave_type_id' => $annual->id, 'from_date' => $from->toDateString(), 'to_date' => $from->copy()->addDays(2)->toDateString()]));
    $service->createLeave($service->validateLeave(['employee_id' => $employee->id, 'leave_type_id' => $unpaid->id, 'from_date' => $from->copy()->addDays(5)->toDateString(), 'to_date' => $from->copy()->addDays(6)->toDateString()]));
    $half = $service->createLeave($service->validateLeave(['employee_id' => $employee->id, 'leave_type_id' => $unpaid->id, 'from_date' => $from->copy()->addDays(10)->toDateString(), 'to_date' => $from->copy()->addDays(10)->toDateString(), 'days' => 0.5]));

    $slip = ($this->slip)($employee, ($this->run)());
    $days = $this->month->daysInMonth;
    expect((float) $slip->days_paid)->toBe($days - 2.5)->and((float) $slip->basic)->toBe(round(100000 * ($days - 2.5) / $days, 2));
    expect($service->balance($employee->id, $annual, (int) $this->month->format('Y')))->toBe(['entitlement' => 20.0, 'taken' => 3.0, 'balance' => 17.0]);
    expect(collect($service->balances((int) $this->month->format('Y')))->firstWhere('leave_type_id', $annual->id)['balance'])->toBe(17.0);

    $service->cancelLeave($half);
    expect($service->monthFacts($this->month)[$employee->id]['unpaid'])->toBe(2.0);
});

it('refuses overlapping leave, more days than the dates, and more than the balance', function (): void {
    $employee = ($this->employee)();
    $annual = ($this->leaveType)(['annual_days' => 5]);
    $service = ($this->attendance)();
    $from = $this->month->copy()->addDays(1);
    $input = ['employee_id' => $employee->id, 'leave_type_id' => $annual->id, 'from_date' => $from->toDateString(), 'to_date' => $from->copy()->addDays(3)->toDateString()];

    $service->createLeave($service->validateLeave($input));
    expect(fn () => $service->createLeave($service->validateLeave($input)))->toThrow(AccountingException::class, 'already has leave')
        ->and(fn () => $service->createLeave($service->validateLeave([...$input, 'from_date' => $from->copy()->addDays(10)->toDateString(), 'to_date' => $from->copy()->addDays(11)->toDateString()])))->toThrow(AccountingException::class, 'Not enough')
        ->and(fn () => $service->createLeave($service->validateLeave([...$input, 'from_date' => $from->copy()->addDays(20)->toDateString(), 'to_date' => $from->copy()->addDays(20)->toDateString(), 'days' => 3])))->toThrow(ValidationException::class)
        ->and(fn () => $service->deleteLeaveType($annual))->toThrow(AccountingException::class);
});

it('pays overtime at the hourly rate for employees flagged for it', function (): void {
    $eligible = ($this->employee)(['overtime_eligible' => true]);
    $other = ($this->employee)(['code' => 'E2', 'name' => 'Bilal']);
    ($this->sheet)(0, [['employee_id' => $eligible->id, 'overtime_hours' => 10, 'holiday_overtime_hours' => 4], ['employee_id' => $other->id, 'overtime_hours' => 10]]);

    $run = ($this->run)();
    $hourly = 100000 / 208;
    $expected = round($hourly * (10 * 2 + 4 * 2), 2);
    $line = PayslipLine::query()->where('payslip_id', ($this->slip)($eligible, $run)->id)->where('description', 'like', 'Overtime%')->firstOrFail();

    expect((float) $line->amount)->toBe($expected)->and($line->description)->toBe('Overtime (10 h + 4 h holiday)')->and((float) ($this->slip)($eligible, $run)->gross)->toBe(round(100000 + $expected, 2))
        ->and((float) ($this->slip)($other, $run)->gross)->toBe(100000.0);
});

it('saves the attendance sheet, clears empty rows and refuses a posted month', function (): void {
    $employee = ($this->employee)();
    expect(($this->sheet)(0, [['employee_id' => $employee->id, 'absent_days' => 2, 'overtime_hours' => 5]]))->toBe(1)->and(Attendance::query()->count())->toBe(1);
    $sheet = ($this->attendance)()->sheet($this->month);
    expect($sheet)->toHaveCount(1)->and($sheet[0]['absent_days'])->toBe('2.00')->and($sheet[0]['overtime_hours'])->toBe('5.00');
    expect(($this->sheet)(0, [['employee_id' => $employee->id, 'absent_days' => 0, 'overtime_hours' => 0]]))->toBe(0)->and(Attendance::query()->count())->toBe(0);

    ($this->payroll)()->post(($this->run)());
    expect(fn () => ($this->sheet)(0, [['employee_id' => $employee->id, 'absent_days' => 1]]))->toThrow(AccountingException::class, 'already posted')
        ->and(fn () => ($this->attendance)()->validateSheet(['month' => $this->month->toDateString(), 'rows' => [['employee_id' => 999999, 'absent_days' => 40]]]))->toThrow(ValidationException::class);
});

it('schedules a loan, pays it out, recovers instalments from salary and closes it', function (): void {
    $employee = ($this->employee)();
    $service = ($this->loans)();
    $loan = $service->create($service->validate(['employee_id' => $employee->id, 'kind' => 'loan', 'principal' => '10000', 'installments' => 3, 'start_month' => $this->month->toDateString()]));

    expect(LoanInstallment::query()->orderBy('id')->pluck('amount')->all())->toBe(['3333.33', '3333.33', '3333.34'])->and($loan->status)->toBe('draft');
    expect(($this->slip)($employee, ($this->run)())->deductions)->toBe('0.00');      // a draft loan is not recovered
    ($this->payroll)()->deleteRun(PayrollRun::query()->firstOrFail());

    $loan = $service->disburse($loan, account('1101')->id, $this->month->toDateString());
    expect($loan->status)->toBe('active')->and(($this->balance)('1105'))->toBe(10000.0)->and(($this->balance)('1101'))->toBe(-10000.0)
        ->and(fn () => $service->disburse($loan, account('1101')->id))->toThrow(AccountingException::class);

    foreach ([0, 1, 2] as $offset) {
        $run = ($this->payroll)()->post(($this->run)($offset));
        expect((float) ($this->slip)($employee, $run)->deductions)->toBe($offset === 2 ? 3333.34 : 3333.33)
            ->and(PayslipLine::query()->where('payslip_id', ($this->slip)($employee, $run)->id)->where('kind', 'deduction')->value('description'))->toBe('Loan instalment '.($offset + 1).'/3');
    }

    expect(($this->balance)('1105'))->toBe(0.0)->and($loan->refresh()->status)->toBe('closed')->and($service->outstanding($loan))->toBe('0.00');
});

it('gives instalments back when a run is voided or deleted, skips one, and settles the rest in cash', function (): void {
    $employee = ($this->employee)();
    $service = ($this->loans)();
    $loan = $service->disburse($service->create($service->validate(['employee_id' => $employee->id, 'kind' => 'advance', 'principal' => '9000', 'installments' => 3, 'start_month' => $this->month->toDateString()])), account('1101')->id);

    $run = ($this->payroll)()->post(($this->run)());
    expect($service->outstanding($loan))->toBe('6000.00');
    ($this->payroll)()->void($run);
    expect($service->outstanding($loan))->toBe('9000.00')->and(LoanInstallment::query()->where('status', 'scheduled')->count())->toBe(3);

    $draft = ($this->run)();
    expect(LoanInstallment::query()->where('status', 'included')->count())->toBe(1)->and(PayslipLine::query()->whereIn('payslip_id', Payslip::query()->where('payroll_run_id', $draft->id)->select('id'))->where('description', 'Advance recovery')->count())->toBe(1)
        ->and(fn () => $service->settle($loan, account('1101')->id))->toThrow(AccountingException::class, 'draft payroll run');
    ($this->payroll)()->deleteRun($draft);
    expect(LoanInstallment::query()->where('status', 'scheduled')->count())->toBe(3);

    $service->skip($loan);
    $schedule = $service->present($loan->refresh())['schedule'];
    expect(collect($schedule)->pluck('status')->all())->toBe(['cancelled', 'scheduled', 'scheduled', 'scheduled'])->and($schedule[3]['due_month'])->toBe($this->month->copy()->addMonths(3)->format('Y-m'));

    $loan = $service->settle($loan, account('1101')->id, $this->month->toDateString());
    expect($loan->status)->toBe('closed')->and(($this->balance)('1105'))->toBe(0.0)->and($loan->settled_amount)->toBe('9000.00');
    expect(fn () => $service->cancel($loan))->toThrow(AccountingException::class);
    $second = $service->create($service->validate(['employee_id' => $employee->id, 'kind' => 'loan', 'principal' => '500', 'installments' => 1, 'start_month' => $this->month->toDateString()]));
    expect($service->cancel($second)->status)->toBe('cancelled')->and(LoanInstallment::query()->where('loan_id', $second->id)->where('status', 'cancelled')->count())->toBe(1);
});

it('takes the employee share of a scheme from pay, books the employer share as a cost, and posts a balanced entry', function (): void {
    $service = ($this->schemes)();
    $scheme = $service->save($service->validate(['code' => 'EOBI', 'name' => 'EOBI', 'base' => 'basic', 'employee_rate' => 1, 'employer_rate' => 5, 'ceiling' => 30000, 'employee_account_id' => account('2102')->id, 'employer_expense_account_id' => account('5102')->id, 'employer_liability_account_id' => account('2102')->id]));
    $employee = ($this->employee)();
    $other = ($this->employee)(['code' => 'E2', 'name' => 'Bilal']);
    expect($service->assign($scheme, ['employee_ids' => [$employee->id]]))->toBe(['changed' => 1])->and($service->assign($scheme, ['employee_ids' => [$employee->id]]))->toBe(['changed' => 0]);

    $run = ($this->payroll)()->post(($this->run)());
    $slip = ($this->slip)($employee, $run);
    // 1% and 5% of the 30,000 ceiling.
    expect($slip->deductions)->toBe('300.00')->and($slip->net)->toBe('99700.00')->and($slip->employer)->toBe('1500.00')->and($run->employer)->toBe('1500.00')
        ->and(($this->slip)($other, $run)->deductions)->toBe('0.00');
    expect(($this->balance)('5102'))->toBe(1500.0)->and(($this->balance)('2102'))->toBe(-1800.0)->and(($this->balance)('5101'))->toBe(200000.0);
    expect(PayslipLine::query()->where('payslip_id', $slip->id)->whereIn('kind', ['employer', 'employer_due'])->count())->toBe(2);

    expect($service->assign($scheme, ['mode' => 'remove', 'employee_ids' => [$employee->id]]))->toBe(['changed' => 1]);
});

it('applies to everybody, to the gross, or as a fixed amount, and checks its accounts', function (): void {
    $service = ($this->schemes)();
    $pf = $service->save($service->validate(['code' => 'PF', 'name' => 'Provident fund', 'base' => 'gross', 'employee_rate' => 8, 'employer_rate' => 8, 'applies_to_all' => true, 'employee_account_id' => account('2102')->id, 'employer_expense_account_id' => account('5102')->id, 'employer_liability_account_id' => account('2102')->id]));
    $fixed = $service->save($service->validate(['code' => 'SESSI', 'name' => 'Social security', 'base' => 'fixed', 'employee_fixed' => 0, 'employer_fixed' => 1250, 'applies_to_all' => true, 'employer_expense_account_id' => account('5102')->id, 'employer_liability_account_id' => account('2102')->id]));
    $hra = PayComponent::query()->create(($this->payroll)()->validateComponent(['code' => 'HRA', 'name' => 'House rent', 'kind' => 'earning', 'method' => 'percent_of_basic', 'value' => 10, 'account_id' => account('5102')->id]));
    $employee = ($this->employee)(['components' => [['pay_component_id' => $hra->id]]]);

    $slip = ($this->slip)($employee, ($this->run)());
    expect($slip->gross)->toBe('110000.00')->and($slip->deductions)->toBe('8800.00')->and($slip->employer)->toBe('10050.00');
    expect(fn () => $service->validate(['code' => 'X', 'name' => 'X', 'base' => 'basic', 'employee_rate' => 1]))->toThrow(ValidationException::class)
        ->and(fn () => $service->validate(['code' => 'Y', 'name' => 'Y', 'base' => 'basic', 'employee_rate' => 0, 'employer_rate' => 0]))->toThrow(ValidationException::class)
        ->and(fn () => $service->validate(['code' => 'Z', 'name' => 'Z', 'base' => 'basic', 'employee_rate' => 1, 'employee_account_id' => account('5102')->id]))->toThrow(ValidationException::class);
    expect($fixed->base)->toBe('fixed')->and($pf->applies_to_all)->toBeTrue();
});

it('also takes contributions on arrears when the scheme says so', function (): void {
    $service = ($this->schemes)();
    $service->save($service->validate(['code' => 'PF', 'name' => 'Provident fund', 'base' => 'basic', 'employee_rate' => 10, 'employer_rate' => 10, 'on_arrears' => true, 'applies_to_all' => true, 'employee_account_id' => account('2102')->id, 'employer_expense_account_id' => account('5102')->id, 'employer_liability_account_id' => account('2102')->id]));
    $employee = ($this->employee)();
    ($this->payroll)()->post(($this->run)(0));
    app(PayrollStructureService::class)->revise($employee, '110000', $this->month->toDateString());
    $arrears = app(PayrollArrearsService::class);
    $arrear = $arrears->create($arrears->validate(['from_month' => $this->month->toDateString(), 'payment_month' => $this->month->copy()->addMonth()->toDateString()]))[0];
    $arrears->approve($arrear);

    $slip = ($this->slip)($employee, ($this->run)(1));
    $lines = PayslipLine::query()->where('payslip_id', $slip->id)->pluck('amount', 'description')->all();
    // 10% of the new 110,000 basic, plus 10% of the 10,000 arrears.
    expect($lines['Provident fund'])->toBe('11000.00')->and($lines['Provident fund on arrears'])->toBe('1000.00')->and($lines['Provident fund (employer)'])->toBe('11000.00')->and($lines['Provident fund on arrears (employer)'])->toBe('1000.00')
        ->and($slip->deductions)->toBe('12000.00')->and($slip->employer)->toBe('12000.00');
});

it('serves the attendance, leave, loan and contribution screens and the API', function (): void {
    $employee = ($this->employee)(['bank_name' => 'HBL', 'bank_account' => 'PK36HABB0000000123456702']);
    ($this->employee)(['code' => 'E2', 'name' => 'No bank']);
    $annual = ($this->leaveType)();

    $this->get('/accounting/payroll/attendance')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/attendance')->has('rows'));
    $this->get('/accounting/payroll/leaves')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/leaves')->has('types', 1));
    $this->get('/accounting/payroll/loans')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/loans'));
    $this->get('/accounting/payroll/schemes')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/schemes')->has('accounts'));
    $this->get("/accounting/payroll/employees/{$employee->id}/edit")->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/employee-form')->has('schemes'));

    Sanctum::actingAs(auth()->user());
    $month = $this->month->format('Y-m');
    $this->postJson('/api/v1/accounting/payroll/attendance', ['month' => $this->month->toDateString(), 'rows' => [['employee_id' => $employee->id, 'absent_days' => 2]]])->assertOk()->assertJsonPath('data.0.absent_days', '2.00');
    $this->getJson("/api/v1/accounting/payroll/attendance?month={$month}")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('month', $month);
    $this->postJson('/api/v1/accounting/payroll/leave-types', ['code' => 'UNP', 'name' => 'Unpaid', 'is_paid' => false])->assertCreated()->assertJsonPath('data.is_paid', false);
    $leave = $this->postJson('/api/v1/accounting/payroll/leaves', ['employee_id' => $employee->id, 'leave_type_id' => $annual->id, 'from_date' => $this->month->toDateString(), 'to_date' => $this->month->copy()->addDay()->toDateString()])->assertCreated()->assertJsonPath('data.days', '2.00')->json('data.id');
    $this->getJson('/api/v1/accounting/payroll/leaves/balances?year='.$this->month->format('Y'))->assertOk()->assertJsonPath('data.0.taken', 2);
    $this->postJson("/api/v1/accounting/payroll/leaves/{$leave}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->deleteJson('/api/v1/accounting/payroll/leave-types/'.LeaveType::query()->where('code', 'UNP')->value('id'))->assertNoContent();

    $loan = $this->postJson('/api/v1/accounting/payroll/loans', ['employee_id' => $employee->id, 'kind' => 'loan', 'principal' => 6000, 'installments' => 2, 'start_month' => $this->month->toDateString()])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonCount(2, 'data.schedule')->json('data.id');
    $this->getJson("/api/v1/accounting/payroll/loans/{$loan}")->assertOk()->assertJsonPath('data.outstanding', '6000.00');
    $this->postJson("/api/v1/accounting/payroll/loans/{$loan}/disburse", ['account_id' => account('1101')->id])->assertForbidden();   // paying out is the approver's
    $scheme = $this->postJson('/api/v1/accounting/payroll/schemes', ['code' => 'PF', 'name' => 'Provident fund', 'base' => 'basic', 'employee_rate' => 5, 'employee_account_id' => account('2102')->id])->assertCreated()->assertJsonPath('data.employees', 0)->json('data.id');
    $this->postJson('/api/v1/accounting/payroll/schemes', ['code' => 'BAD', 'name' => 'Bad', 'base' => 'basic', 'employee_rate' => 5])->assertUnprocessable();
    $this->postJson("/api/v1/accounting/payroll/schemes/{$scheme}/assign", ['employee_ids' => [$employee->id]])->assertOk()->assertJsonPath('data.changed', 1);
    $this->getJson("/api/v1/accounting/payroll/schemes/{$scheme}")->assertOk()->assertJsonPath('data.employees', 1);
    $this->putJson("/api/v1/accounting/payroll/schemes/{$scheme}", ['code' => 'PF', 'name' => 'PF fund', 'base' => 'basic', 'employee_rate' => 6, 'employee_account_id' => account('2102')->id])->assertOk()->assertJsonPath('data.name', 'PF fund');

    $approver = User::factory()->create();
    $approver->assignRole('approver');
    Sanctum::actingAs($approver);
    $this->postJson("/api/v1/accounting/payroll/loans/{$loan}/disburse", ['account_id' => account('1101')->id, 'date' => $this->month->toDateString()])->assertOk()->assertJsonPath('data.status', 'active');
    $this->postJson("/api/v1/accounting/payroll/loans/{$loan}/skip")->assertForbidden();   // the accountant manages the schedule
    $run = ($this->payroll)()->post(($this->run)());

    // 2 absent days of 31 leave 93,548.39; less 6% provident fund (5,612.90) and the first 3,000 instalment.
    $this->getJson('/api/v1/accounting/payroll/runs/'.$run->id)->assertOk()->assertJsonPath('data.payslips.0.net', '84935.49');
    $this->getJson("/api/v1/accounting/payroll/runs/{$run->id}/bank-file/csv?preview=1")->assertOk()->assertJsonPath('count', 1)->assertJsonPath('missing.0.code', 'E2')->assertJsonPath('total', '84935.49')->assertJsonPath('data.0.Employee code', 'E1')
        ->assertJsonPath('data.0.Narration', 'Salary '.$this->month->format('F Y'));
    $csv = $this->get("/api/v1/accounting/payroll/runs/{$run->id}/bank-file/csv?layout=iban")->assertOk()->streamedContent();
    expect($csv)->toContain('"Account / IBAN","Employee name",Amount,Narration')->toContain('PK36HABB0000000123456702')->not->toContain('E2');
    $this->get("/api/v1/accounting/payroll/runs/{$run->id}/bank-file/xlsx")->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->getJson("/api/v1/accounting/payroll/runs/{$run->id}/bank-file/csv?layout=nope&preview=1")->assertUnprocessable();
    $draft = ($this->payroll)()->createRun($this->month->copy()->addMonth()->toDateString());
    $this->getJson("/api/v1/accounting/payroll/runs/{$draft->id}/bank-file/csv?preview=1")->assertUnprocessable();
});

it('keeps overtime to flagged employees and the sheet out of reach of viewers', function (): void {
    ($this->employee)();
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    $this->actingAs($viewer);

    $this->get('/accounting/payroll/attendance')->assertOk();
    $this->post('/accounting/payroll/attendance', ['month' => $this->month->toDateString(), 'rows' => []])->assertForbidden();
    $this->post('/accounting/payroll/loans', ['employee_id' => 1])->assertForbidden();
    $this->post('/accounting/payroll/schemes', ['code' => 'X'])->assertForbidden();
    $this->get('/accounting/payroll/runs/'.($this->run)()->id.'/bank-file/csv')->assertForbidden();
});

it('accepts the blank fields the screens send for optional numbers', function (): void {
    // The screens send "" for fields left empty (turned into null on the way in): they must mean "none", not an error.
    $this->post('/accounting/payroll/components', ['code' => 'CONV', 'name' => 'Conveyance', 'kind' => 'earning', 'method' => 'fixed', 'value' => 3000, 'rate' => '', 'unit' => '', 'taxable' => 1, 'account_id' => account('5102')->id])->assertRedirect()->assertSessionHasNoErrors();
    expect(PayComponent::query()->firstOrFail()->rate)->toBe('0.0000');
    $this->post('/accounting/payroll/leave-types', ['code' => 'UNP', 'name' => 'Unpaid', 'is_paid' => 0, 'annual_days' => ''])->assertRedirect()->assertSessionHasNoErrors();
    expect(LeaveType::query()->firstOrFail()->annual_days)->toBe('0.00');
    $this->post('/accounting/payroll/schemes', ['code' => 'EOBI', 'name' => 'EOBI', 'base' => 'basic', 'employee_rate' => 1, 'employer_rate' => '', 'employee_fixed' => '', 'employer_fixed' => '', 'ceiling' => '', 'employee_account_id' => account('2102')->id])->assertRedirect()->assertSessionHasNoErrors();
    expect(ContributionScheme::query()->firstOrFail()->employer_rate)->toBe('0.0000');
});
