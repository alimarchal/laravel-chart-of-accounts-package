<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Loan;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Services\FbrService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollLoanService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->admin = tap(User::factory()->create())->assignRole('admin');
    $this->features = fn () => app(FeatureManager::class);
});

it('has every feature on by default and describes each one', function (): void {
    foreach (FeatureManager::catalog() as $key => $entry) {
        expect(($this->features)()->enabled($key))->toBeTrue()
            ->and($entry['parent'] === null || array_key_exists($entry['parent'], FeatureManager::catalog()))->toBeTrue()
            ->and($entry['paths'])->not->toBe([]);
    }
});

it('finds the feature that owns a path, the most specific first', function (): void {
    $features = ($this->features)();

    expect($features->forPath('payroll/loans/5/skip'))->toBe('payroll_loans')
        ->and($features->forPath('payroll/runs/3/bank-file/csv'))->toBe('payroll_bank_file')
        ->and($features->forPath('payroll/runs/3'))->toBe('payroll')
        ->and($features->forPath('payroll/tax'))->toBe('payroll_reports')
        ->and($features->forPath('tax-codes/2'))->toBe('tax')
        ->and($features->forPath('chart-of-accounts/4/control-type'))->toBe('control_accounts')
        ->and($features->forPath('chart-of-accounts'))->toBeNull()
        ->and($features->relative('api/v1/accounting/payroll/loans'))->toBe('payroll/loans')
        ->and($features->relative('accounting/payroll'))->toBe('payroll');
});

it('turns a feature off, answers its routes with 404 and brings them back', function (): void {
    Sanctum::actingAs(tap(User::factory()->create())->assignRole('accountant'));
    $this->getJson('/api/v1/accounting/inventory')->assertOk();

    ($this->features)()->set('inventory', false);
    $this->getJson('/api/v1/accounting/inventory')->assertNotFound()->assertJsonPath('message', 'The Inventory feature is turned off.');
    $this->getJson('/api/v1/accounting/chart-of-accounts')->assertOk();   // the core is never switched

    ($this->features)()->set('inventory', true);
    $this->getJson('/api/v1/accounting/inventory')->assertOk();
});

it('lets a sub-feature go off while payroll stays, and a parent takes its children with it', function (): void {
    Sanctum::actingAs(tap(User::factory()->create())->assignRole('accountant'));
    ($this->features)()->set('payroll_loans', false);

    $this->getJson('/api/v1/accounting/payroll/loans')->assertNotFound();
    $this->getJson('/api/v1/accounting/payroll/employees')->assertOk();

    ($this->features)()->set('payroll_loans', true);
    ($this->features)()->set('payroll', false);
    expect(($this->features)()->enabled('payroll_loans'))->toBeFalse()->and(($this->features)()->own('payroll_loans'))->toBeTrue();
    $this->getJson('/api/v1/accounting/payroll/loans')->assertNotFound();
});

it('keeps loans out of the payslips while the loans feature is off', function (): void {
    $this->actingAs(tap(User::factory()->create())->assignRole('accountant'));
    $month = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $payroll = app(PayrollService::class);
    $employee = $payroll->saveEmployee($payroll->validateEmployee(['code' => 'E1', 'name' => 'Amina', 'join_date' => $month->copy()->subYear()->toDateString(), 'base_salary' => '100000']));
    $loans = app(PayrollLoanService::class);
    $loan = $loans->create($loans->validate(['employee_id' => $employee->id, 'kind' => 'advance', 'principal' => 9000, 'installments' => 3, 'start_month' => $month->toDateString()]));
    $this->actingAs(tap(User::factory()->create())->assignRole('approver'));
    $loans->disburse($loan, account('1101')->id, $month->toDateString());

    ($this->features)()->set('payroll_loans', false);
    $run = $payroll->createRun($month->toDateString());
    expect(PayslipLine::query()->where('description', 'like', 'Advance%')->count())->toBe(0);
    $payroll->deleteRun($run);

    ($this->features)()->set('payroll_loans', true);
    $payroll->createRun($month->toDateString());
    expect(PayslipLine::query()->where('description', 'like', 'Advance%')->count())->toBe(1)->and(Loan::query()->firstOrFail()->status)->toBe('active');
});

it('follows the config default until an administrator decides, and fbr needs its own setting too', function (): void {
    config(['accounting.features.disabled' => ['budgets']]);
    $features = ($this->features)();
    expect($features->enabled('budgets'))->toBeFalse();

    $features->set('budgets', true);
    expect($features->enabled('budgets'))->toBeTrue();
    $features->reset('budgets');
    expect($features->enabled('budgets'))->toBeFalse();

    config(['accounting.fbr.enabled' => true]);
    expect(app(FbrService::class)->enabled())->toBeTrue();
    $features->set('fbr', false);
    expect(app(FbrService::class)->enabled())->toBeFalse();
});

it('records every change in the audit trail and refuses a name it does not know', function (): void {
    $this->actingAs($this->admin);
    ($this->features)()->set('tax', false);
    ($this->features)()->set('tax', false);   // no change, no entry

    expect(AccountingAuditLog::query()->where('action', 'FEATURE_DISABLED')->count())->toBe(1);
    expect(fn () => ($this->features)()->set('chart-of-accounts', false))->toThrow(AccountingException::class);
});

it('is exposed over the API for administrators only', function (): void {
    Sanctum::actingAs($this->admin);
    $list = $this->getJson('/api/v1/accounting/features')->assertOk()->json('data');
    expect(collect($list)->pluck('key')->all())->toContain('payroll', 'payroll_loans', 'inventory');

    $this->putJson('/api/v1/accounting/features', ['features' => ['inventory' => false, 'payroll_loans' => 'false']])->assertOk()->assertJsonPath('message', 'Features saved.');
    expect(($this->features)()->own('inventory'))->toBeFalse()->and(($this->features)()->own('payroll_loans'))->toBeFalse();
    $this->putJson('/api/v1/accounting/features', ['features' => ['nonsense' => true]])->assertUnprocessable();
    $this->putJson('/api/v1/accounting/features', ['features' => ['tax' => 'maybe']])->assertUnprocessable();
    $this->putJson('/api/v1/accounting/features', [])->assertUnprocessable();

    Sanctum::actingAs(tap(User::factory()->create())->assignRole('viewer'));
    $this->getJson('/api/v1/accounting/features')->assertForbidden();
    $this->putJson('/api/v1/accounting/features', ['features' => ['tax' => false]])->assertForbidden();
});

it('lists, disables, enables and resets from the artisan command', function (): void {
    $this->artisan('accounting:features')->expectsOutputToContain('payroll_loans')->assertSuccessful();
    $this->artisan('accounting:features', ['action' => 'disable', 'features' => ['inventory', 'fixed_assets']])->assertSuccessful();
    expect(($this->features)()->own('inventory'))->toBeFalse();

    $this->artisan('accounting:features', ['action' => 'enable', 'features' => ['inventory']])->assertSuccessful();
    $this->artisan('accounting:features', ['action' => 'reset', 'features' => ['fixed_assets']])->assertSuccessful();
    expect(($this->features)()->enabled('inventory'))->toBeTrue()->and(($this->features)()->enabled('fixed_assets'))->toBeTrue();

    $this->artisan('accounting:features', ['action' => 'disable', 'features' => ['all']])->assertSuccessful();
    expect(($this->features)()->enabled('payroll'))->toBeFalse();
    $this->artisan('accounting:features', ['action' => 'disable', 'features' => ['nope']])->assertFailed();
    $this->artisan('accounting:features', ['action' => 'enable'])->assertExitCode(2);
});

it('stops the scheduled commands of a feature that is off', function (): void {
    ($this->features)()->set('payroll', false);
    $this->artisan('accounting:payroll-run')->expectsOutputToContain('turned off')->assertSuccessful();

    ($this->features)()->set('recurring_entries', false);
    $this->artisan('accounting:run-recurring')->expectsOutputToContain('turned off')->assertSuccessful();
});
