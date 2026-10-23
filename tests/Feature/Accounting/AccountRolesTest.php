<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Support\AccountRoles;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);
    $this->admin = tap(User::factory()->create())->assignRole('super-admin');
    Sanctum::actingAs($this->admin);
});

it('lists the roles and which account plays each', function (): void {
    $rows = collect($this->getJson('/api/v1/accounting/account-roles')->assertOk()->assertJsonPath('chart', 'general')->json('data'))->keyBy('role');

    expect($rows['cash']['accounts'][0]['account_code'])->toBe('1101')
        ->and($rows['bank']['accounts'][0]['account_code'])->toBe('1102')
        ->and($rows['aging.receivables']['multiple'])->toBeTrue()
        ->and(collect($rows['aging.receivables']['accounts'])->pluck('account_code')->all())->toBe(['1103', '1104'])
        ->and($rows->keys()->all())->toContain('salary_expense', 'income_tax_payable', 'employee_advances', 'retained_earnings');
});

it('changes the account of a role, audits it, and the modules follow', function (): void {
    $this->putJson('/api/v1/accounting/account-roles', ['roles' => ['cash' => [account('1108')->id], 'aging.receivables' => [account('1103')->id]]])
        ->assertOk()->assertJsonPath('message', 'Account roles saved.');

    expect(AccountRoles::code('cash'))->toBe('1108')
        ->and(AccountRoles::codes('aging.receivables'))->toBe(['1103']);
    $log = AccountingAuditLog::query()->where('action', 'ACCOUNT_ROLES_UPDATED')->latest('id')->firstOrFail();
    expect($log->old_values['cash'])->toBe(['1101'])->and($log->new_values['cash'])->toBe(['1108']);

    // The roles it protects: the account that now plays "cash" can no longer be deactivated.
    $this->putJson('/api/v1/accounting/chart-of-accounts/'.account('1108')->id, ['parent_id' => account('1108')->parent_id, 'account_type_id' => account('1108')->account_type_id, 'currency_id' => account('1108')->currency_id, 'account_code' => '1108', 'account_name' => account('1108')->account_name, 'normal_balance' => 'debit', 'is_active' => false])
        ->assertStatus(422);
});

it('refuses roles it cannot honour', function (): void {
    $put = fn (array $roles) => $this->putJson('/api/v1/accounting/account-roles', ['roles' => $roles]);

    $put(['cash' => [account('1101')->id, account('1108')->id]])->assertStatus(422)->assertJsonValidationErrors('roles.cash');   // one account only
    $put(['employee_advances' => [account('1100')->id]])->assertStatus(422)->assertJsonValidationErrors('roles.employee_advances');    // a group, where only a posting account will do
    $put(['cash' => [account('1100')->id]])->assertOk();                                                                            // the cash book adds up a group's sub-accounts
    $put(['aging.receivables' => [account('1100')->id]])->assertOk();
    AccountRoles::set('cash', [account('1101')->id]);
    $put(['cash' => [999999]])->assertStatus(422);
    $put(['moon' => [account('1101')->id]])->assertStatus(422)->assertJsonValidationErrors('roles.moon');

    config(['accounting.defaults.cash_account_code' => '1101']);
    $put(['cash' => [account('1108')->id]])->assertStatus(422)->assertJsonValidationErrors('roles.cash');                          // fixed by config
    expect(AccountRoles::code('cash'))->toBe('1101');
});

it('keeps the screen to people who manage settings', function (): void {
    $accountant = tap(User::factory()->create())->assignRole('accountant');
    Sanctum::actingAs($accountant);

    $this->getJson('/api/v1/accounting/account-roles')->assertForbidden();
    $this->putJson('/api/v1/accounting/account-roles', ['roles' => ['cash' => [account('1101')->id]]])->assertForbidden();
});

it('shows the roles on the React screen and saves them from a form', function (): void {
    $this->actingAs($this->admin);

    $this->get('/accounting/account-roles')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/account-roles/index')->where('chart', 'general')->has('roles')->has('accounts'));
    $this->put('/accounting/account-roles', ['roles' => ['bank' => [account('1108')->id], 'employee_advances' => ['']]])->assertRedirect()->assertSessionHas('success', 'Account roles saved.');

    expect(AccountRoles::code('bank'))->toBe('1108')->and(AccountRoles::code('employee_advances'))->toBe('1105');      // none chosen: the chart's own default
})->skip(fn () => config('accounting.ui_driver') === 'blade', 'React screens are not served with the Blade driver');

it('lists, sets and seeds roles from the command line', function (): void {
    Artisan::call('accounting:account-roles', ['action' => 'set', 'role' => 'cash', 'codes' => ['1108']]);
    expect(AccountRoles::code('cash'))->toBe('1108');

    expect(Artisan::call('accounting:account-roles', ['action' => 'set', 'role' => 'cash', 'codes' => ['1108', '1101']]))->not->toBe(0)         // one account
        ->and(Artisan::call('accounting:account-roles', ['action' => 'set', 'role' => 'cash', 'codes' => ['9999']]))->not->toBe(0)                 // no such account
        ->and(Artisan::call('accounting:account-roles', ['action' => 'set', 'role' => 'moon', 'codes' => ['1101']]))->not->toBe(0);

    Artisan::call('accounting:account-roles', ['action' => 'seed', 'codes' => ['general'], '--overwrite' => true]);
    expect(AccountRoles::code('cash'))->toBe('1101');

    Artisan::call('accounting:account-roles');
    expect(Artisan::output())->toContain('salary_expense')->toContain('Cash In Hand');
});

it('renders the Blade screen and saves from its form', function (): void {
    $this->actingAs($this->admin);

    $this->get(route('settings.account-roles.index'))->assertOk()->assertSee('Account roles')->assertSee('roles[cash][]', false)->assertSee('Cash In Hand');
    $this->put(route('settings.account-roles.update'), ['roles' => ['cash' => [account('1108')->id]]])->assertRedirect();

    expect(AccountRoles::code('cash'))->toBe('1108');
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('refuses an account of the wrong type for a role', function (): void {
    $this->putJson('/api/v1/accounting/account-roles', ['roles' => ['retained_earnings' => [account('5102')->id]]])->assertUnprocessable()->assertJsonValidationErrors('roles.retained_earnings');
    $this->putJson('/api/v1/accounting/account-roles', ['roles' => ['cash' => [account('2101')->id]]])->assertUnprocessable()->assertJsonValidationErrors('roles.cash');
    $this->putJson('/api/v1/accounting/account-roles', ['roles' => ['salary_expense' => [account('1101')->id]]])->assertUnprocessable();

    expect(AccountRoles::code('retained_earnings'))->toBe('3101')->and(AccountRoles::code('cash'))->toBe('1101');

    $this->putJson('/api/v1/accounting/account-roles', ['roles' => ['retained_earnings' => [account('3102')->id], 'cash' => [account('1108')->id]]])->assertOk();
    expect(AccountRoles::code('retained_earnings'))->toBe('3102');

    $this->artisan('accounting:account-roles', ['action' => 'set', 'role' => 'cash', 'codes' => ['2101']])->assertFailed();
});

it('does not close a year into a retained-earnings account that is not equity', function (): void {
    AccountRoles::set('retained_earnings', [account('5102')->id]);
    journal(['1101' => 1000, '4101' => -1000]);

    expect(fn () => app(\Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction::class)->execute(\Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod::query()->where('status', 'open')->firstOrFail()))
        ->toThrow(\Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException::class, 'not an equity account');
});
