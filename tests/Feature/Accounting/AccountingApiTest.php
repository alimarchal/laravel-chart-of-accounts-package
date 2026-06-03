<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    Sanctum::actingAs($user);
});

it('returns chart of accounts through the versioned API', function (): void {
    $this->getJson('/api/v1/accounting/chart-of-accounts')
        ->assertSuccessful()
        ->assertJsonCount(15, 'data');
});

it('shows a chart account through route model binding', function (): void {
    $account = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();

    $this->getJson("/api/v1/accounting/chart-of-accounts/{$account->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.account_code', '1101');
});

it('returns validation errors for invalid journal API payloads', function (): void {
    $this->postJson('/api/v1/accounting/journal-entries', [
        'entry_date' => now()->toDateString(),
        'lines' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['lines']);
});
