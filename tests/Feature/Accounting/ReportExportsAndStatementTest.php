<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Reports\AccountStatementReport;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);

    $this->day = fn (int $n): string => now()->startOfYear()->addDays($n)->toDateString();
});

it('builds a statement with opening, running and closing balances', function (): void {
    journal(['1101' => 1000, '4101' => -1000], ($this->day)(1));   // before the range
    journal(['1101' => 250, '4101' => -250], ($this->day)(5));
    journal(['5104' => 100, '1101' => -100], ($this->day)(6));

    $cash = app(AccountStatementReport::class)->statement(account('1101'), ($this->day)(3), ($this->day)(10));

    expect($cash['opening_balance'])->toBe('1000.00')
        ->and($cash['entries']->getCollection()->pluck('running_balance')->all())->toBe(['1250.00', '1150.00'])
        ->and($cash['totals'])->toBe(['debit' => '250.00', 'credit' => '100.00', 'closing_balance' => '1150.00']);

    // Credit-normal accounts read positive for credits.
    $revenue = app(AccountStatementReport::class)->statement(account('4101'));
    expect($revenue['opening_balance'])->toBe('0.00')
        ->and($revenue['entries']->getCollection()->pluck('running_balance')->all())->toBe(['1000.00', '1250.00'])
        ->and($revenue['totals']['closing_balance'])->toBe('1250.00');
});

it('keeps the running balance correct on later pages', function (): void {
    foreach (range(1, 5) as $i) {
        journal(['1101' => 10, '4101' => -10], ($this->day)($i));
    }

    $page = app(AccountStatementReport::class)->statement(account('1101'), perPage: 2);
    expect($page['entries']->lastPage())->toBe(3);

    request()->merge(['page' => 3]);
    $last = app(AccountStatementReport::class)->statement(account('1101'), perPage: 2);

    expect($last['entries']->getCollection()->pluck('running_balance')->all())->toBe(['50.00']);
});

it('serves the statement over the API with balances and pagination', function (): void {
    journal(['1101' => 300, '4101' => -300], ($this->day)(2));

    $this->getJson('/api/v1/accounting/reports/account-statement?account_code=1101')
        ->assertOk()
        ->assertJsonPath('account.account_code', '1101')
        ->assertJsonPath('opening_balance', '0.00')
        ->assertJsonPath('totals.closing_balance', '300.00')
        ->assertJsonPath('data.0.running_balance', '300.00')
        ->assertJsonPath('total', 1);

    $this->getJson('/api/v1/accounting/reports/account-statement?account_code=NOPE')->assertNotFound();
});

it('loads nothing on the statement page until an account is chosen', function (): void {
    journal(['1101' => 300, '4101' => -300], ($this->day)(2));

    $this->get('/accounting/reports/account-statement')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounting/reports/account-statement')
            ->where('statement', null)
            ->has('accounts'));

    $this->get('/accounting/reports/account-statement?account_id='.account('1101')->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('statement.totals.closing_balance', '300.00')
            ->has('statement.entries.data', 1));
});

it('gives the general ledger page its filters and accounts', function (): void {
    journal(['1101' => 300, '4101' => -300], ($this->day)(2));

    $this->get('/accounting/reports/general-ledger?account_id='.account('1101')->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounting/reports/general-ledger')
            ->where('filters.account_id', (string) account('1101')->id)
            ->has('entries.data', 1)
            ->has('accounts'));
});

it('checks the export permission before running any query', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->get('/accounting/reports/general-ledger/export/csv')->assertForbidden();

    expect(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'vw_accounting_general_ledger')))->toBeEmpty();
});

it('streams csv exports, including the bank and cash books', function (string $report): void {
    journal(['1101' => 300, '4101' => -300], ($this->day)(2));

    $response = $this->get("/accounting/reports/{$report}/export/csv");
    $response->assertOk();

    expect($response->streamedContent())->toBeString();
})->with(['general-ledger', 'account-statement', 'bank-book', 'cash-book']);

it('refuses xlsx and pdf exports above the row limit', function (): void {
    config(['accounting.export_max_rows.xlsx' => 3, 'accounting.export_max_rows.pdf' => 3]);
    journal(['1101' => 10, '4101' => -10], ($this->day)(1));
    journal(['1101' => 10, '4101' => -10], ($this->day)(2));

    $this->get('/accounting/reports/general-ledger/export/xlsx')->assertStatus(422);
    $this->get('/accounting/reports/general-ledger/export/pdf')->assertStatus(422);
    $this->get('/accounting/reports/general-ledger/export/csv')->assertOk();

    config(['accounting.export_max_rows.xlsx' => 4]);
    $this->get('/accounting/reports/general-ledger/export/xlsx')->assertOk();
});

it('writes a readable multi-page pdf', function (): void {
    foreach (range(1, 30) as $i) {
        journal(['1101' => $i, '4101' => -$i], ($this->day)(1), reference: "REF-{$i}");
    }

    $pdf = $this->get('/accounting/reports/general-ledger/export/pdf')->assertOk()->getContent();

    expect($pdf)->toStartWith('%PDF-1.4')
        ->toContain('/Count 2')          // 60 lines over two pages
        ->toMatch('/T\* \([^)]*REF-30 /')  // one row per text line
        ->toContain('page 2 of 2');
});

it('names spreadsheet columns past Z correctly and keeps codes as text', function (): void {
    $row = array_combine(array_map(fn ($i) => "c{$i}", range(0, 27)), array_fill(0, 28, 'x'));
    $row['c0'] = '0012';
    $row['c1'] = '1250.50';

    $xlsx = app(AccountingReportExporter::class)->download([$row], 'wide', 'xlsx')->getContent();
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $xlsx);
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    expect($sheet)->toContain('r="AA2"')->toContain('r="AB2"')
        ->toContain('<c r="A2" t="inlineStr"><is><t>0012</t></is></c>')
        ->toContain('<c r="B2"><v>1250.50</v></c>');
})->skip(! class_exists(ZipArchive::class), 'ext-zip is not installed');

it('paginates the cash flow while totals cover the whole period', function (): void {
    foreach (range(1, 5) as $i) {
        journal(['1101' => 100, '4101' => -100], ($this->day)($i));
    }
    journal(['5104' => 30, '1101' => -30], ($this->day)(6));

    $this->getJson('/api/v1/accounting/reports/cash-flow?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('total', 6)
        ->assertJsonPath('totals', ['cash_in' => '500.00', 'cash_out' => '30.00', 'net_cash_flow' => '470.00']);

    $this->get('/accounting/reports/cash-flow')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows.data', 6)
            ->where('totals.net_cash_flow', '470.00'));
});
