<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);
});

function accountPayload(ChartOfAccount $account, array $overrides = []): array
{
    return array_merge([
        'parent_id' => $account->parent_id,
        'account_type_id' => $account->account_type_id,
        'currency_id' => $account->currency_id,
        'account_code' => $account->account_code,
        'account_name' => $account->account_name,
        'normal_balance' => $account->normal_balance,
    ], $overrides);
}

it('keeps is_active and is_group unchanged when an update omits them', function (): void {
    $account = account('5104');

    $this->putJson("/api/v1/accounting/chart-of-accounts/{$account->id}", accountPayload($account, ['account_name' => 'Office Supplies']))
        ->assertSuccessful();

    $account->refresh();
    expect($account->account_name)->toBe('Office Supplies')
        ->and($account->is_active)->toBeTrue()
        ->and($account->is_group)->toBeFalse();
});

it('creates accounts active by default and derives the normal balance from the account type', function (): void {
    $parent = account('5100');

    $response = $this->postJson('/api/v1/accounting/chart-of-accounts', [
        'parent_id' => $parent->id,
        'account_type_id' => $parent->account_type_id,
        'currency_id' => $parent->currency_id,
        'account_code' => '5199',
        'account_name' => 'Training Expense',
    ])->assertCreated();

    expect($response->json('data.is_active'))->toBeTrue()
        ->and(account('5199')->normal_balance)->toBe('debit');
});

it('rejects making an account its own parent or a child of its descendant', function (): void {
    $group = account('1100');

    $this->putJson("/api/v1/accounting/chart-of-accounts/{$group->id}", accountPayload($group, ['parent_id' => $group->id]))
        ->assertUnprocessable();

    $descendant = account('1102'); // Bank Accounts group under 1100
    $this->putJson("/api/v1/accounting/chart-of-accounts/{$group->id}", accountPayload($group, ['parent_id' => $descendant->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'An account cannot be moved under one of its own descendants.');

    expect($group->fresh()->parent_id)->toBe(account('1000')->id);
});

it('requires the parent to be a group account of the same type', function (): void {
    $expense = account('5104');

    $this->putJson("/api/v1/accounting/chart-of-accounts/{$expense->id}", accountPayload($expense, ['parent_id' => account('5101')->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The parent account must be a group account.');

    $this->putJson("/api/v1/accounting/chart-of-accounts/{$expense->id}", accountPayload($expense, ['parent_id' => account('1100')->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A child account must have the same account type as its parent.');
});

it('locks structural fields once an account has journal entries', function (): void {
    journal(['5104' => 100, '1101' => -100]);
    $account = account('5104');
    $assetType = AccountType::query()->where('code', 'ASSET')->value('id');

    $this->putJson("/api/v1/accounting/chart-of-accounts/{$account->id}", accountPayload($account, ['account_type_id' => $assetType, 'parent_id' => null]))
        ->assertUnprocessable();
    $this->putJson("/api/v1/accounting/chart-of-accounts/{$account->id}", accountPayload($account, ['account_code' => '5999']))
        ->assertUnprocessable();
    $this->putJson("/api/v1/accounting/chart-of-accounts/{$account->id}", accountPayload($account, ['is_group' => true]))
        ->assertUnprocessable();

    // Renaming and deactivating are still allowed.
    $this->putJson("/api/v1/accounting/chart-of-accounts/{$account->id}", accountPayload($account, ['account_name' => 'Supplies', 'is_active' => false]))
        ->assertSuccessful();
});

it('does not let a group with children become a posting account', function (): void {
    $group = account('1150');

    $this->putJson("/api/v1/accounting/chart-of-accounts/{$group->id}", accountPayload($group, ['is_group' => false]))
        ->assertUnprocessable();
});

it('protects accounts referenced by the accounting defaults config', function (): void {
    $retained = account('3101');

    $this->putJson("/api/v1/accounting/chart-of-accounts/{$retained->id}", accountPayload($retained, ['account_code' => '3199']))
        ->assertUnprocessable();
    $this->putJson("/api/v1/accounting/chart-of-accounts/{$retained->id}", accountPayload($retained, ['is_active' => false]))
        ->assertUnprocessable();
});

it('returns 422 instead of 500 when deleting accounts that are in use', function (): void {
    $parent = account('5100');
    $leaf = app(ChartOfAccountService::class)->create([
        'parent_id' => $parent->id,
        'account_type_id' => $parent->account_type_id,
        'currency_id' => $parent->currency_id,
        'account_code' => '5198',
        'account_name' => 'Temp',
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);
    journal(['5198' => 50, '1101' => -50]);

    $this->deleteJson("/api/v1/accounting/chart-of-accounts/{$leaf->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Accounts with journal entries cannot be deleted. Deactivate the account instead.');

    $this->deleteJson('/api/v1/accounting/chart-of-accounts/'.account('1100')->id)->assertUnprocessable();
});

it('deletes an unused non-system account', function (): void {
    $parent = account('5100');
    $leaf = app(ChartOfAccountService::class)->create([
        'parent_id' => $parent->id,
        'account_type_id' => $parent->account_type_id,
        'currency_id' => $parent->currency_id,
        'account_code' => '5197',
        'account_name' => 'Unused',
        'normal_balance' => 'debit',
    ]);

    $this->deleteJson("/api/v1/accounting/chart-of-accounts/{$leaf->id}")->assertNoContent();
});

it('builds the account tree with a constant number of queries', function (): void {
    DB::enableQueryLog();
    $roots = app(ChartOfAccountService::class)->tree();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $countNodes = function ($nodes) use (&$countNodes): int {
        return $nodes->sum(fn ($node) => 1 + $countNodes($node->childrenRecursive));
    };

    expect($queries)->toBeLessThanOrEqual(2)
        ->and($roots->pluck('account_code')->all())->toBe(['1000', '2000', '3000', '4000', '5000'])
        ->and($roots->first()->childrenRecursive->pluck('account_code')->all())->toContain('1100')
        ->and($countNodes($roots))->toBe(ChartOfAccount::query()->count());
});
