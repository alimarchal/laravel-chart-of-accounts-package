<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Mail\PayslipMail;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\LeaveType;
use Alimarchal\LaravelChartOfAccounts\Models\Loan;
use Alimarchal\LaravelChartOfAccounts\Models\LoanInstallment;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\Settlement;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollLoanService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollPayslipService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollReportService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollSettlementService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollTaxReportService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->actingAs(tap(User::factory()->create())->assignRole('accountant'));
    $this->month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->payroll = fn () => app(PayrollService::class);
    $this->settlements = fn () => app(PayrollSettlementService::class);
    $this->employee = function (array $overrides = []): Employee {
        $service = ($this->payroll)();

        return $service->saveEmployee($service->validateEmployee(['code' => 'E1', 'name' => 'Amina', 'join_date' => $this->month->copy()->subYears(5)->toDateString(), 'base_salary' => '90000', ...$overrides]));
    };
    $this->run = fn (int $offset = 0) => ($this->payroll)()->post(($this->payroll)()->createRun($this->month->copy()->addMonths($offset)->toDateString()));
    $this->balance = fn (string $code): float => (float) DB::table('accounting_journal_entry_lines')->where('chart_of_account_id', account($code)->id)->selectRaw('COALESCE(SUM(base_debit),0) - COALESCE(SUM(base_credit),0) as net')->value('net');
});

it('works out gratuity, leave pay and the loans owed into a net settlement', function (): void {
    config(['accounting.payroll.gratuity.leave_type' => 'ANN']);
    $employee = ($this->employee)();
    $type = LeaveType::query()->create(['code' => 'ANN', 'name' => 'Annual', 'is_paid' => true, 'annual_days' => 20]);
    $loans = app(PayrollLoanService::class);
    $loans->disburse($loans->create($loans->validate(['employee_id' => $employee->id, 'kind' => 'loan', 'principal' => 12000, 'installments' => 4, 'start_month' => $this->month->toDateString()])), account('1101')->id);

    $leave = $this->month->copy()->addMonths(3)->addDays(14);
    $work = ($this->settlements)()->calculate(['employee_id' => $employee->id, 'leave_date' => $leave->toDateString()]);
    $years = round($employee->join_date->diffInDays($leave) / 365.25, 2);

    // 90,000 / 30 x 30 days x the years served; 20 unused leave days at 90,000 / 30; less the 12,000 owed.
    expect((float) $work['service_years'])->toBe($years)->and((float) $work['gratuity'])->toBe(round(90000 / 30 * 30 * $years, 2))->and((float) $work['leave_encashment'])->toBe(60000.0)
        ->and($work['loan_recovery'])->toBe('12000.00')->and((float) $work['net'])->toBe(round(90000 * $years + 60000 - 12000, 2))->and($work['loans'])->toHaveCount(1);

    $override = ($this->settlements)()->calculate(['employee_id' => $employee->id, 'leave_date' => $leave->toDateString(), 'gratuity' => 100000, 'leave_days' => 3, 'adjustment' => -5000]);
    expect($override['gratuity'])->toBe('100000.00')->and($override['leave_encashment'])->toBe('9000.00')->and($override['net'])->toBe('92000.00');
    expect(fn () => ($this->settlements)()->calculate(['employee_id' => $employee->id, 'leave_date' => $employee->join_date->copy()->subDay()->toDateString()]))->toThrow(ValidationException::class)
        ->and($type->annual_days)->toBe('20.00');
});

it('pays no gratuity before the minimum service', function (): void {
    $employee = ($this->employee)(['join_date' => $this->month->copy()->subMonths(6)->toDateString()]);

    expect(($this->settlements)()->calculate(['employee_id' => $employee->id, 'leave_date' => $this->month->toDateString()])['gratuity'])->toBe('0.00');
});

it('posts a settlement, recovers the loans, pays it, and restores everything on a void', function (): void {
    $employee = ($this->employee)();
    $loans = app(PayrollLoanService::class);
    $loan = $loans->disburse($loans->create($loans->validate(['employee_id' => $employee->id, 'kind' => 'loan', 'principal' => 12000, 'installments' => 4, 'start_month' => $this->month->copy()->addMonths(3)->toDateString()])), account('1101')->id);
    $service = ($this->settlements)();
    $data = $service->validate(['employee_id' => $employee->id, 'leave_date' => $this->month->copy()->addMonths(1)->toDateString(), 'gratuity' => 50000, 'adjustment' => 10000]);

    $settlement = $service->create($data);
    expect($settlement->status)->toBe('draft')->and($settlement->net)->toBe('48000.00');
    expect($service->create($data)->id)->not->toBe($settlement->id)->and(Settlement::query()->count())->toBe(1);   // a draft is replaced

    $settlement = $service->post(Settlement::query()->firstOrFail(), null, $this->month->copy()->addMonths(1)->toDateString());
    expect($settlement->status)->toBe('posted')->and(($this->balance)('5101'))->toBe(60000.0)->and(($this->balance)('2103'))->toBe(-48000.0)->and(($this->balance)('1105'))->toBe(0.0)
        ->and($loan->refresh()->status)->toBe('closed')->and($loans->outstanding($loan))->toBe('0.00')->and(LoanInstallment::query()->where('status', 'scheduled')->count())->toBe(0)
        ->and($employee->refresh()->leave_date->toDateString())->toBe($this->month->copy()->addMonths(1)->toDateString());
    expect(fn () => $service->post($settlement))->toThrow(AccountingException::class)->and(fn () => $service->create($data))->toThrow(AccountingException::class, 'already has a settlement');

    $paid = $service->pay($settlement, account('1101')->id, $this->month->copy()->addMonths(2)->toDateString());
    expect($paid->status)->toBe('paid')->and(($this->balance)('2103'))->toBe(0.0);
    $void = $service->void($paid);
    expect($void->status)->toBe('void')->and(($this->balance)('5101'))->toBe(0.0)->and(($this->balance)('1101'))->toBe(-12000.0)->and($loan->refresh()->status)->toBe('active')
        ->and($loans->outstanding($loan))->toBe('12000.00')->and(LoanInstallment::query()->where('status', 'scheduled')->count())->toBe(4);
});

it('refuses loans bigger than the settlement and a draft that is not a draft', function (): void {
    $employee = ($this->employee)(['join_date' => $this->month->copy()->subMonths(3)->toDateString()]);
    $loans = app(PayrollLoanService::class);
    $loans->disburse($loans->create($loans->validate(['employee_id' => $employee->id, 'kind' => 'advance', 'principal' => 9000, 'installments' => 3, 'start_month' => $this->month->toDateString()])), account('1101')->id);
    $service = ($this->settlements)();

    expect(fn () => $service->create($service->validate(['employee_id' => $employee->id, 'leave_date' => $this->month->toDateString()])))->toThrow(AccountingException::class, 'loans owed');
    $draft = $service->create($service->validate(['employee_id' => $employee->id, 'leave_date' => $this->month->toDateString(), 'adjustment' => 9000]));
    $service->delete($draft);
    expect(Settlement::query()->count())->toBe(0);
});

it('prints the tax certificate of a tax year month by month and the annual statement of everybody', function (): void {
    $cost = CostCenter::query()->create(['code' => 'HO', 'name' => 'Head office', 'type' => 'cost_center']);
    $taxed = ($this->employee)(['base_salary' => '250000', 'withhold_tax' => true, 'cost_center_id' => $cost->id, 'national_id' => '12345-1234567-1']);
    ($this->employee)(['code' => 'E2', 'name' => 'Bilal']);
    foreach ([0, 1, 2] as $offset) {
        ($this->run)($offset);
    }

    $report = app(PayrollTaxReportService::class);
    $start = (int) $this->month->format('Y');
    config(['accounting.payroll.tax_year_start_month' => 1]);
    $certificate = $report->certificate($taxed, $start);

    expect($certificate['months'])->toHaveCount(3)->and($certificate['totals'])->toBe(['taxable' => '750000.00', 'tax' => '75000.00', 'gross' => '750000.00', 'net' => '675000.00'])
        ->and($certificate['employee']['national_id'])->toBe('12345-1234567-1')->and($certificate['label'])->toBe('Jan '.$start.' – Dec '.$start);
    $annual = $report->annual($start);
    expect($annual)->toHaveCount(2)->and($annual[0]['Tax withheld'])->toBe('75000.00')->and($annual[1]['Tax withheld'])->toBe('0.00')->and($annual[1]['Months paid'])->toBe('3');
    config(['accounting.payroll.tax_year_start_month' => 7]);
    expect($report->taxYear(2026)['label'])->toBe('Jul 2026 – Jun 2027')->and($report->certificate($taxed, 1999)['months'])->toBe([]);
});

it('compares months, costs cost centers and counts heads', function (): void {
    $cost = CostCenter::query()->create(['code' => 'HO', 'name' => 'Head office', 'type' => 'cost_center']);
    ($this->employee)(['cost_center_id' => $cost->id]);
    ($this->employee)(['code' => 'E2', 'name' => 'Bilal', 'base_salary' => '60000']);
    ($this->employee)(['code' => 'E3', 'name' => 'Leaver', 'join_date' => $this->month->copy()->subYear()->toDateString(), 'leave_date' => $this->month->copy()->addMonths(1)->endOfMonth()->toDateString()]);
    ($this->run)(0);
    ($this->run)(1);
    $reports = app(PayrollReportService::class);
    $year = (int) $this->month->format('Y');

    $comparison = $reports->comparison($year);
    expect($comparison)->toHaveCount(2)->and($comparison[0]['employees'])->toBe(3)->and($comparison[0]['gross'])->toBe('240000.00')->and($comparison[0]['change'])->toBeNull()
        ->and($comparison[1]['change'])->toBe('0.00')->and($comparison[0]['cost_per_employee'])->toBe('80000.00');
    $centers = $reports->costCenters($year.'-01', $year.'-12');
    expect($centers)->toHaveCount(2)->and($centers[0]['cost_center'])->toBe('No cost center')->and($centers[0]['pay'])->toBe('300000.00')->and($centers[1]['cost_center'])->toBe('HO Head office')->and($centers[1]['employees'])->toBe(1);
    $heads = $reports->headcount($year);
    expect($heads[0]['on_payroll'])->toBe(3)->and($heads[1]['left'])->toBe(1)->and($heads[2]['on_payroll'])->toBe(2);
});

it('renders a payslip document and mails it with the document attached', function (): void {
    Mail::fake();
    $employee = ($this->employee)(['email' => 'amina@example.test']);
    ($this->employee)(['code' => 'E2', 'name' => 'No mail']);
    $run = ($this->run)();
    $service = app(PayrollPayslipService::class);
    $slip = Payslip::query()->where('employee_id', $employee->id)->firstOrFail();

    $file = $service->render($run, $slip);
    expect($file['mime'])->toBe('application/pdf')->and($file['content'])->toStartWith('%PDF')->and($file['filename'])->toBe('payslip-E1-'.$this->month->format('Y-m').'.pdf');
    $service->email($run, $slip);
    Mail::assertSent(PayslipMail::class, fn (PayslipMail $mail) => $mail->hasTo('amina@example.test') && $mail->subject === 'Payslip for '.$this->month->format('F Y'));

    $result = $service->emailRun($run);
    expect($result['sent'])->toBe(1)->and($result['skipped'])->toBe([['code' => 'E2', 'name' => 'No mail']]);
    expect(fn () => $service->email($run, Payslip::query()->where('employee_id', '<>', $employee->id)->firstOrFail()))->toThrow(AccountingException::class, 'no e-mail');
    $draft = ($this->payroll)()->createRun($this->month->copy()->addMonth()->toDateString());
    expect(fn () => $service->emailRun($draft))->toThrow(AccountingException::class, 'Post the payroll first');
});

it('creates the draft run of the month by command, once', function (): void {
    ($this->employee)();
    $this->artisan('accounting:payroll-run', ['--month' => $this->month->format('Y-m')])->assertSuccessful();
    expect(PayrollRun::query()->count())->toBe(1)->and(PayrollRun::query()->firstOrFail()->status)->toBe('draft')->and(PayrollRun::query()->firstOrFail()->notes)->toBe('Created by the scheduler');
    $this->artisan('accounting:payroll-run', ['--month' => $this->month->format('Y-m')])->assertSuccessful();
    expect(PayrollRun::query()->count())->toBe(1);
    $this->artisan('accounting:payroll-run', ['--month' => $this->month->copy()->addMonth()->format('Y-m'), '--company' => 'NOPE'])->assertSuccessful();
    expect(PayrollRun::query()->count())->toBe(1);
});

it('serves settlements, reports, tax pages, payslip documents and the API', function (): void {
    Mail::fake();
    config(['accounting.payroll.tax_year_start_month' => 1]);
    $employee = ($this->employee)(['email' => 'amina@example.test', 'withhold_tax' => true]);
    $run = ($this->run)();
    $slip = Payslip::query()->firstOrFail();
    $year = (int) $this->month->format('Y');

    $this->get('/accounting/payroll/settlements')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/settlements'));
    $this->get('/accounting/payroll/reports?year='.$year)->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/reports')->has('comparison', 1));
    $this->get('/accounting/payroll/tax?year='.$year)->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/tax'));
    $this->get("/accounting/payroll/tax/certificate/{$employee->id}?year={$year}")->assertOk()->assertInertia(fn ($page) => $page->component('accounting/payroll/tax-certificate')->where('certificate.employee.code', 'E1'));
    $this->get("/accounting/payroll/runs/{$run->id}/payslips/{$slip->id}/print")->assertOk()->assertSee('Payslip')->assertSee('Net pay');
    $this->get("/accounting/payroll/runs/{$run->id}/payslips/{$slip->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->post("/accounting/payroll/runs/{$run->id}/payslips/{$slip->id}/email")->assertRedirect()->assertSessionHas('success');
    $this->post("/accounting/payroll/runs/{$run->id}/email-payslips")->assertRedirect()->assertSessionHas('success');
    Mail::assertSent(PayslipMail::class, 2);

    Sanctum::actingAs(auth()->user());
    $this->getJson("/api/v1/accounting/payroll/reports?year={$year}")->assertOk()->assertJsonPath('data.comparison.0.employees', 1)->assertJsonPath('data.headcount.0.month', $year.'-01');
    $this->get("/api/v1/accounting/payroll/reports/comparison/export/csv?year={$year}")->assertOk();
    $this->get('/api/v1/accounting/payroll/reports/nope/export/csv')->assertNotFound();
    $this->getJson("/api/v1/accounting/payroll/tax?year={$year}")->assertOk()->assertJsonPath('data.rows.0.Employee code', 'E1');
    $this->get("/api/v1/accounting/payroll/tax/annual/csv?year={$year}")->assertOk();
    $this->getJson("/api/v1/accounting/payroll/tax/certificate/{$employee->id}?year={$year}")->assertOk()->assertJsonPath('data.months.0.month', $this->month->format('Y-m'));

    $preview = $this->postJson('/api/v1/accounting/payroll/settlements/preview', ['employee_id' => $employee->id, 'leave_date' => $this->month->copy()->addMonth()->toDateString(), 'gratuity' => 1000])->assertOk()->assertJsonPath('data.gratuity', '1000.00')->json('data');
    $id = $this->postJson('/api/v1/accounting/payroll/settlements', ['employee_id' => $employee->id, 'leave_date' => $this->month->copy()->addMonth()->toDateString(), 'gratuity' => 1000])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
    $this->getJson('/api/v1/accounting/payroll/settlements')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/accounting/payroll/settlements/{$id}")->assertOk()->assertJsonPath('data.net', $preview['net']);
    $this->postJson("/api/v1/accounting/payroll/settlements/{$id}/post")->assertForbidden();   // posting is the approver's
    $approver = User::factory()->create();
    $approver->assignRole('approver');
    Sanctum::actingAs($approver);
    $this->postJson("/api/v1/accounting/payroll/settlements/{$id}/post", ['date' => $this->month->copy()->addMonth()->toDateString()])->assertOk()->assertJsonPath('data.status', 'posted');
    $this->postJson("/api/v1/accounting/payroll/settlements/{$id}/pay", ['account_id' => account('1101')->id, 'date' => $this->month->copy()->addMonths(2)->toDateString()])->assertOk()->assertJsonPath('data.status', 'paid');
    $this->postJson("/api/v1/accounting/payroll/settlements/{$id}/void")->assertOk()->assertJsonPath('data.status', 'void');
    $this->deleteJson("/api/v1/accounting/payroll/settlements/{$id}")->assertForbidden();
    expect(Loan::query()->count())->toBe(0);
});
