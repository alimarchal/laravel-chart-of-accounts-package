<?php

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Tests\BladeTestCase;
use Alimarchal\LaravelChartOfAccounts\Tests\TestCase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class)->in('Feature');
uses(BladeTestCase::class)->in('Blade');

/**
 * Run a statement that is expected to fail in a savepoint: PostgreSQL aborts the surrounding test
 * transaction on any error, which would break the rest of the test and its teardown.
 */
function savepoint(callable $statement): mixed
{
    return DB::transaction($statement);
}

function account(string $code): ChartOfAccount
{
    return ChartOfAccount::query()->where('account_code', $code)->firstOrFail();
}

/**
 * Create (and by default post) a journal entry from [account_code => signed amount] pairs:
 * positive = debit, negative = credit.
 *
 * @param  array<string, int|float|string>  $amounts
 */
function journal(array $amounts, ?string $date = null, bool $post = true, ?string $reference = null): JournalEntry
{
    $lines = [];

    foreach ($amounts as $code => $amount) {
        $lines[] = [
            'chart_of_account_id' => account((string) $code)->id,
            'debit' => $amount > 0 ? $amount : 0,
            'credit' => $amount < 0 ? -$amount : 0,
        ];
    }

    return app(JournalEntryService::class)->create([
        'entry_date' => $date ?? now()->toDateString(),
        'reference' => $reference,
        'auto_post' => $post,
        'lines' => $lines,
    ]);
}
