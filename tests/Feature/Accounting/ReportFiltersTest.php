<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);
    $this->user = tap(User::factory()->create())->assignRole('super-admin');
    Sanctum::actingAs($this->user);
    $this->day = fn (int $n): string => now()->startOfYear()->addDays($n)->toDateString();

    journal(['1101' => 1000, '4101' => -1000], ($this->day)(1));
    journal(['1101' => 250, '4101' => -250], ($this->day)(40));
    journal(['5104' => 100, '1101' => -100], ($this->day)(80));
});

it('reads the trial balance as of a date, for one period, and filtered by account', function (): void {
    $get = fn (string $query) => collect($this->getJson("/api/v1/accounting/reports/trial-balance?{$query}")->assertOk()->json('data'))->mapWithKeys(fn (array $row) => ['c'.$row['account_code'] => $row]);

    // Everything / as of the middle / only the movement of the middle
    expect((float) $get('')->get('c1101')['balance'])->toBe(1150.0)
        ->and((float) $get('as_of_date='.($this->day)(50))->get('c1101')['balance'])->toBe(1250.0)
        ->and((float) $get('as_of_date='.($this->day)(10))->get('c1101')['balance'])->toBe(1000.0)
        ->and((float) $get('as_of_date='.($this->day)(50).'&date_from='.($this->day)(20))->get('c1101')['total_debits'])->toBe(250.0)
        ->and((float) $get('as_of_date='.($this->day)(50).'&date_from='.($this->day)(20))->get('c4101')['balance'])->toBe(250.0);

    // Totals follow the rows: debits equal credits at every date.
    foreach ([10, 50, 90] as $n) {
        $totals = $this->getJson('/api/v1/accounting/reports/trial-balance?as_of_date='.($this->day)($n))->json('totals');
        expect(round($totals['total_debit'] - $totals['total_credit'], 2))->toBe(0.0);
    }

    expect($get('search=cash in hand')->keys()->map(fn ($key) => substr($key, 1))->all())->toContain('1101')->not->toContain('4101')
        ->and($get('search=1101')->keys()->map(fn ($key) => substr($key, 1))->all())->toBe(['1101'])
        ->and($get('hide_zero=1')->keys()->map(fn ($key) => substr($key, 1))->all())->toEqualCanonicalizing(['1101', '4101', '5104'])
        ->and($get('hide_zero=1&as_of_date='.($this->day)(10))->keys()->map(fn ($key) => substr($key, 1))->all())->toEqualCanonicalizing(['1101', '4101'])   // 5104 has no entry yet
        ->and($get('account_type=Income,Expense')->keys()->map(fn ($key) => substr($key, 1))->all())->toContain('4101', '5104')->not->toContain('1101');
    $this->getJson('/api/v1/accounting/reports/trial-balance?as_of_date=nope')->assertStatus(422);
    $this->getJson('/api/v1/accounting/reports/trial-balance?date_from='.($this->day)(30).'&as_of_date='.($this->day)(10))->assertStatus(422);
});

it('exports what is on screen: the date, the period and the filters travel with the export', function (): void {
    $csv = fn (string $report, string $query) => $this->get("/accounting/reports/{$report}/export/csv?{$query}")->assertOk()->streamedContent();
    $table = function (string $report, string $query, string $key) use ($csv): array {
        $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv($report, $query)))));
        $header = array_shift($lines);

        return collect($lines)->mapWithKeys(fn (array $line) => ['c'.array_combine($header, $line)[$key] => array_combine($header, $line)])->all();
    };

    $early = $table('trial-balance', 'as_of_date='.($this->day)(10), 'account_code');
    $late = $table('trial-balance', 'as_of_date='.($this->day)(90), 'account_code');
    expect((float) $early['c1101']['balance'])->toBe(1000.0)
        ->and((float) $late['c1101']['balance'])->toBe(1150.0)
        ->and(array_keys($table('trial-balance', 'search=1101', 'account_code')))->toBe(['c1101']);

    $profit = fn (int $to) => collect($table('income-statement', 'date_from='.($this->day)(0).'&date_to='.($this->day)($to), 'account_code'))->map(fn (array $row) => (float) $row['balance'])->all();
    expect($profit(10)['c4101'])->toBe(1000.0)
        ->and($profit(100)['c4101'])->toBe(1250.0)
        ->and($profit(100)['c5104'])->toBe(100.0)
        ->and($profit(10))->not->toHaveKey('c5104');

    $sheet = fn (int $to) => collect($table('balance-sheet', 'as_of_date='.($this->day)($to), 'account_code'))->map(fn (array $row) => (float) $row['balance'])->all();
    expect($sheet(10)['c1101'])->toBe(1000.0)
        ->and($sheet(90)['c1101'])->toBe(1150.0);
});

it('keeps the date on the React report pages and carries it into the export links', function (): void {
    $this->actingAs($this->user);

    $this->get('/accounting/reports/trial-balance?as_of_date='.($this->day)(10).'&search=cash in hand')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/reports/trial-balance')->where('filters.as_of_date', ($this->day)(10))->where('filters.search', 'cash in hand')->has('rows', 1));
    $this->get('/accounting/reports/aged-receivables?as_of_date='.($this->day)(10))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/reports/aged-receivables')->where('filters.as_of_date', ($this->day)(10)));
    $this->get('/accounting/reports/aged-payables?as_of_date='.($this->day)(10))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/reports/aged-payables')->where('filters.as_of_date', ($this->day)(10)));
    $this->get('/accounting/reports/balance-sheet?as_of_date='.($this->day)(10))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.as_of_date', ($this->day)(10)));
    $this->get('/accounting/reports/income-statement?date_from='.($this->day)(0).'&date_to='.($this->day)(10))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.date_from', ($this->day)(0))->where('filters.date_to', ($this->day)(10)));
    $this->get('/accounting/reports/aged-receivables?as_of_date=nope')->assertSessionHasErrors('as_of_date');
});

it('shows the account rows and the filters on the Blade trial balance', function (): void {
    $this->actingAs($this->user);

    $html = $this->get('/accounting/reports/trial-balance?as_of_date='.($this->day)(10).'&search=cash+in+hand')->assertOk()->getContent();

    expect($html)->toContain('Cash In Hand')->toContain('1,000.00')->not->toContain('Accounts Receivable')->toContain('value="cash in hand"');
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');
