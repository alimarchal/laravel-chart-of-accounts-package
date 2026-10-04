<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Laravel\Sanctum\Sanctum;

const API = '/api/v1/accounting';

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super-admin');
    Sanctum::actingAs($this->user);
});

it('creates and posts an entry using account codes instead of ids', function (): void {
    $this->postJson(API.'/journal-entries', [
        'entry_date' => now()->toDateString(),
        'reference' => 'INV-1001',
        'currency_code' => 'PKR',
        'auto_post' => true,
        'lines' => [
            ['account_code' => '1103', 'debit' => '1500.50', 'credit' => 0, 'cost_center_code' => 'ADMIN'],
            ['account_code' => '4101', 'debit' => 0, 'credit' => '1500.50'],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.total_debit', '1500.50')
        ->assertJsonPath('data.total_credit', '1500.50')
        ->assertJsonPath('data.lines.0.account_code', '1103')
        ->assertJsonPath('data.lines.1.account_code', '4101');
})->skip(fn () => ! CostCenter::query()->where('code', 'ADMIN')->exists(), 'seeded cost centre code differs');

it('explains unknown account codes and unbalanced entries', function (): void {
    $this->postJson(API.'/journal-entries', [
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_code' => '9999', 'debit' => 10, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 10],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('lines.0.account_code');

    $this->postJson(API.'/journal-entries', [
        'entry_date' => now()->toDateString(),
        'auto_post' => true,
        'lines' => [
            ['account_code' => '1101', 'debit' => 10, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 9],
        ],
    ])->assertUnprocessable()->assertJsonPath('message', 'Journal entry is not balanced.');

    expect(JournalEntry::query()->count())->toBe(0);
});

it('records a simple two-line entry in one call', function (): void {
    $this->postJson(API.'/journal-entries/simple', [
        'debit_account_code' => '5102',
        'credit_account_code' => '1101',
        'amount' => 2500,
        'description' => 'Office rent',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.total_debit', '2500.00');

    $this->postJson(API.'/journal-entries/simple', [
        'debit_account_code' => '5102',
        'credit_account_code' => '5102',
        'amount' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors(['credit_account_code', 'amount']);
});

it('makes retries safe with an Idempotency-Key', function (): void {
    $payload = ['debit_account_code' => '5102', 'credit_account_code' => '1101', 'amount' => 100];

    $first = $this->withHeader('Idempotency-Key', 'rent-2026-10')->postJson(API.'/journal-entries/simple', $payload)->assertCreated();
    $retry = $this->withHeader('Idempotency-Key', 'rent-2026-10')->postJson(API.'/journal-entries/simple', $payload)
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($retry->json('data.id'))->toBe($first->json('data.id'))
        ->and(JournalEntry::query()->count())->toBe(1);

    $this->withHeader('Idempotency-Key', 'rent-2026-10')
        ->postJson(API.'/journal-entries/simple', array_merge($payload, ['amount' => 999]))
        ->assertUnprocessable();
});

it('runs the full journal lifecycle over the API', function (): void {
    $draftId = $this->postJson(API.'/journal-entries', [
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_code' => '5104', 'debit' => 40, 'credit' => 0],
            ['account_code' => '1101', 'debit' => 0, 'credit' => 40],
        ],
    ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

    $lines = $this->getJson(API."/journal-entries/{$draftId}")->json('data.lines');

    $this->putJson(API."/journal-entries/{$draftId}", [
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['id' => $lines[0]['id'], 'account_code' => '5104', 'debit' => 45, 'credit' => 0],
            ['id' => $lines[1]['id'], 'account_code' => '1101', 'debit' => 0, 'credit' => 45],
        ],
    ])->assertOk()->assertJsonPath('data.total_debit', '45.00')->assertJsonPath('data.lines.0.id', $lines[0]['id']);

    $this->postJson(API."/journal-entries/{$draftId}/post")->assertOk()->assertJsonPath('data.status', 'posted');
    $this->putJson(API."/journal-entries/{$draftId}", ['entry_date' => now()->toDateString(), 'lines' => [
        ['account_code' => '5104', 'debit' => 1, 'credit' => 0], ['account_code' => '1101', 'debit' => 0, 'credit' => 1],
    ]])->assertUnprocessable();

    $reversalId = $this->postJson(API."/journal-entries/{$draftId}/reverse", ['description' => 'Wrong amount'])
        ->assertOk()
        ->assertJsonPath('data.reverses_entry_id', $draftId)
        ->json('data.id');

    $this->getJson(API."/journal-entries/{$draftId}")->assertJsonPath('data.is_reversed', true)->assertJsonPath('data.reversed_by_entry_id', $reversalId);

    $voidId = $this->postJson(API.'/journal-entries', [
        'entry_date' => now()->toDateString(),
        'lines' => [['account_code' => '5104', 'debit' => 1, 'credit' => 0], ['account_code' => '1101', 'debit' => 0, 'credit' => 1]],
    ])->json('data.id');
    $this->postJson(API."/journal-entries/{$voidId}/void")->assertOk()->assertJsonPath('data.status', 'void');
});

it('filters, sorts and includes lines when listing entries', function (): void {
    journal(['5104' => 10, '1101' => -10], reference: 'A-1');
    journal(['5104' => 20, '1101' => -20], reference: 'B-1', post: false);

    $this->getJson(API.'/journal-entries?filter[status]=posted&include=lines')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference', 'A-1')
        ->assertJsonCount(2, 'data.0.lines');

    $this->getJson(API.'/journal-entries?sort=reference')->assertOk()->assertJsonPath('data.0.reference', 'A-1');
    $this->getJson(API.'/journal-entries?sort=-reference')->assertOk()->assertJsonPath('data.0.reference', 'B-1');
    $this->getJson(API.'/journal-entries?sort=password')->assertStatus(400);
});

it('returns account balances including child accounts, and the account tree', function (): void {
    journal(['1108' => 300, '4101' => -300]);
    journal(['1109' => 200, '4101' => -200]);

    $this->getJson(API.'/chart-of-accounts/'.account('1102')->id.'/balance')
        ->assertOk()
        ->assertJsonPath('data.balance', '500.00')
        ->assertJsonPath('data.includes_child_accounts', true);

    $this->getJson(API.'/chart-of-accounts/'.account('4101')->id.'/balance')->assertJsonPath('data.balance', '500.00');

    $this->getJson(API.'/chart-of-accounts/tree')
        ->assertOk()
        ->assertJsonPath('data.0.account_code', '1000')
        ->assertJsonPath('data.0.children.0.account_code', '1100');
});

it('serves every financial report as JSON', function (): void {
    journal(['1101' => 5000, '3103' => -5000]);
    journal(['1103' => 1200, '4101' => -1200]);
    journal(['5104' => 300, '1101' => -300]);

    $this->getJson(API.'/reports/trial-balance')->assertOk()->assertJsonPath('totals.difference', 0);
    $this->getJson(API.'/reports/balance-sheet')->assertOk()->assertJsonPath('totals.difference', 0)->assertJsonPath('totals.assets', 5900);
    $this->getJson(API.'/reports/income-statement')->assertOk()
        ->assertJsonPath('totals.revenue', '1200.00')
        ->assertJsonPath('totals.expenses', '300.00')
        ->assertJsonPath('totals.net_income', '900.00');
    $this->getJson(API.'/reports/general-ledger?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('total', 6);
    $this->getJson(API.'/reports/cash-book')->assertOk()->assertJsonPath('totals.total_debit', 5000);
    $this->getJson(API.'/reports/bank-book')->assertOk()->assertJsonPath('total', 0);
    $this->getJson(API.'/reports/cash-flow')->assertOk()->assertJsonPath('totals.net_cash_flow', '4700.00');
    $this->getJson(API.'/reports/aged-receivables')->assertOk()->assertJsonPath('data.0.account_code', '1103');
    $this->getJson(API.'/reports/aged-payables')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(API.'/reports/account-statement?account_code=1101')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson(API.'/reports/account-statement')->assertUnprocessable();
    $this->getJson(API.'/reports/income-statement?date_from=2026-12-01&date_to=2026-01-01')->assertUnprocessable();
});

it('closes, reopens and year-end closes periods over the API', function (): void {
    $period = AccountingPeriod::query()->firstOrFail();
    journal(['1101' => 800, '4101' => -800], $period->start_date->copy()->addDay()->toDateString());

    $this->postJson(API."/periods/{$period->id}/close")->assertOk()->assertJsonPath('data.status', 'closed');
    $this->postJson(API."/periods/{$period->id}/close")->assertUnprocessable();
    $this->postJson(API."/periods/{$period->id}/reopen")->assertOk()->assertJsonPath('data.status', 'open');
    $this->postJson(API."/periods/{$period->id}/close-fiscal-year")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closing_net_income', '800.00');
});

it('reports installation health', function (): void {
    $this->getJson(API.'/health')->assertOk()->assertJsonPath('ok', true);
});
