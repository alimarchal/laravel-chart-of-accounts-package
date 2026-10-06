<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Services\DashboardService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    $this->today = now()->startOfDay();
    $this->overview = fn () => app(DashboardService::class)->overview($this->accountant, $this->today->toDateString(), 3);
});

it('reports income, expense and the trend by month, leaving out closing entries', function (): void {
    journal(['1101' => 1000, '4101' => -1000], $this->today->toDateString());
    journal(['5102' => 400, '1101' => -400], $this->today->toDateString());
    journal(['1101' => 300, '4101' => -300], $this->today->copy()->subMonthNoOverflow()->toDateString());

    $performance = ($this->overview)()['performance'];

    expect($performance['this_month'])->toBe(['income' => '1000.00', 'expense' => '400.00', 'net' => '600.00'])
        ->and($performance['last_month']['income'])->toBe('300.00')
        ->and($performance['trend'])->toHaveCount(3)
        ->and(end($performance['trend']))->toBe(['month' => $this->today->format('Y-m'), 'income' => '1000.00', 'expense' => '400.00'])
        ->and($performance['top_expenses'][0]['amount'])->toBe('400.00');
});

it('sums the bank accounts that are linked to a ledger account', function (): void {
    BankAccount::query()->create(['chart_of_account_id' => account('1101')->id, 'account_name' => 'Main', 'bank_name' => 'Test Bank', 'account_number' => '1', 'is_active' => true]);
    journal(['1101' => 2500, '4101' => -2500], $this->today->toDateString());

    $cash = ($this->overview)()['cash'];

    expect($cash['total'])->toBe('2500.00')->and($cash['accounts'][0]['name'])->toBe('Main');
});

it('raises an alert for drafts and shows recent entries', function (): void {
    journal(['1101' => 50, '4101' => -50], $this->today->toDateString(), post: false);

    $overview = ($this->overview)();

    expect(collect($overview['alerts'])->pluck('key'))->toContain('drafts')
        ->and($overview['recent_entries'])->toHaveCount(1)
        ->and($overview['recent_entries'][0]['amount'])->toBe('50.00');
});

it('leaves out the sections a user may not see', function (): void {
    $limited = User::factory()->create();
    $limited->givePermissionTo('accounting.view');

    $overview = app(DashboardService::class)->overview($limited, $this->today->toDateString());

    expect($overview['performance'])->toBeNull()->and($overview['cash'])->toBeNull()->and($overview['receivables'])->toBeNull()
        ->and($overview['payables'])->toBeNull()->and($overview['recent_entries'])->toBeNull()->and($overview['alerts'])->toBe([]);
});

it('serves the React page, the Blade page and the API', function (): void {
    journal(['1101' => 70, '4101' => -70], $this->today->toDateString());

    $this->get(route('accounting.overview'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/overview')->where('overview.performance.this_month.income', '70.00'));

    Sanctum::actingAs($this->accountant);
    $this->getJson('/api/v1/accounting/dashboard?months=2')->assertOk()
        ->assertJsonPath('data.performance.this_month.income', '70.00')->assertJsonCount(2, 'data.performance.trend');
    $this->getJson('/api/v1/accounting/dashboard?months=99')->assertUnprocessable();
});
