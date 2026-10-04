<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);
});

it('supports create, read, update, list and delete for every master-data resource', function (string $uri, Closure $payload, string $field, mixed $updated): void {
    $base = "/api/v1/accounting/{$uri}";

    $id = $this->postJson($base, $payload())->assertCreated()->json('data.id');

    $this->getJson("{$base}/{$id}")->assertSuccessful()->assertJsonPath('data.id', $id);
    $this->getJson("{$base}?per_page=2")->assertSuccessful()->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']]);

    $this->putJson("{$base}/{$id}", array_merge($payload(), [$field => $updated]))
        ->assertSuccessful()
        ->assertJsonPath("data.{$field}", $updated);

    $this->deleteJson("{$base}/{$id}")->assertNoContent();
    $this->getJson("{$base}/{$id}")->assertNotFound();
})->with([
    'account types' => ['account-types', fn () => ['code' => 'CONTRA', 'name' => 'Contra Asset', 'normal_balance' => 'credit', 'report_group' => 'BalanceSheet'], 'name', 'Contra Assets'],
    'currencies' => ['currencies', fn () => ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'exchange_rate_to_base' => 1.9], 'name', 'Yen'],
    'cost centers' => ['cost-centers', fn () => ['code' => 'OPS', 'name' => 'Operations', 'type' => 'cost_center'], 'name', 'Ops'],
    'bank accounts' => ['bank-accounts', fn () => ['account_name' => 'Main', 'account_number' => 'PK00-1', 'chart_of_account_id' => account('1108')->id], 'bank_name', 'HBL'],
    'tax codes' => ['tax-codes', fn () => ['code' => 'VAT5', 'name' => 'VAT 5%'], 'name', 'VAT five'],
    'tax rates' => ['tax-rates', fn () => ['tax_code_id' => TaxCode::query()->value('id'), 'rate' => 5, 'effective_from' => '2031-01-01'], 'is_active', false],
    'reconciliations' => ['reconciliations', fn () => ['bank_account_id' => BankAccount::factory()->create()->id, 'statement_date' => '2026-01-31', 'statement_balance' => 100, 'book_balance' => 100, 'status' => 'draft'], 'status', 'completed'],
]);

it('rejects linking a bank account to a group account', function (): void {
    $this->postJson('/api/v1/accounting/bank-accounts', [
        'account_name' => 'Main',
        'account_number' => 'PK00-2',
        'chart_of_account_id' => account('1102')->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('chart_of_account_id');
});

it('returns 422 instead of 500 when deleting master data that is in use', function (): void {
    $type = account('1101')->account_type_id;

    $this->deleteJson("/api/v1/accounting/account-types/{$type}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This record is in use by other accounting records and cannot be deleted.');
});

it('keeps exactly one base currency', function (): void {
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    $pkr = Currency::query()->where('code', 'PKR')->firstOrFail();

    $this->putJson("/api/v1/accounting/currencies/{$pkr->id}", ['code' => 'PKR', 'name' => 'Rupee', 'exchange_rate_to_base' => 1, 'is_base' => false])
        ->assertUnprocessable();

    // Before anything is posted the base currency may be switched; the old base is demoted.
    $this->putJson("/api/v1/accounting/currencies/{$usd->id}", ['code' => 'USD', 'name' => 'US Dollar', 'exchange_rate_to_base' => 1, 'is_base' => true])
        ->assertSuccessful();
    expect(Currency::query()->where('is_base', true)->pluck('code')->all())->toBe(['USD']);
});

it('refuses to change the base currency once entries are posted', function (): void {
    journal(['1101' => 10, '4101' => -10]);
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();

    $this->putJson("/api/v1/accounting/currencies/{$usd->id}", ['code' => 'USD', 'name' => 'US Dollar', 'exchange_rate_to_base' => 1, 'is_base' => true])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The base currency cannot be changed once journal entries have been posted.');
});

it('rejects a duplicate tax rate with a validation error', function (): void {
    $rate = TaxRate::query()->firstOrFail();

    $this->postJson('/api/v1/accounting/tax-rates', [
        'tax_code_id' => $rate->tax_code_id,
        'rate' => 1,
        'effective_from' => $rate->effective_from->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('effective_from');
});

it('lists balance snapshots after a period close', function (): void {
    $period = AccountingPeriod::query()->firstOrFail();
    $this->postJson("/api/v1/accounting/periods/{$period->id}/close")->assertSuccessful()->assertJsonPath('data.status', 'closed');

    $id = $this->getJson('/api/v1/accounting/account-balance-snapshots?filter[accounting_period_id]='.$period->id)
        ->assertSuccessful()
        ->json('data.0.id');

    $this->getJson("/api/v1/accounting/account-balance-snapshots/{$id}")->assertSuccessful();
});
