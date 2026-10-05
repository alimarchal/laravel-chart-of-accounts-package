<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountImportService;
use Alimarchal\LaravelChartOfAccounts\Support\SpreadsheetReader;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('super-admin');
    $this->actingAs($this->owner);
    Sanctum::actingAs($this->owner);

    $this->csv = fn (string $content, string $name = 'chart.csv') => UploadedFile::fake()->createWithContent($name, $content);
    $this->import = app(ChartOfAccountImportService::class);
});

it('imports new accounts with parents defined later in the file, defaulting type and currency', function (): void {
    $file = ($this->csv)(<<<'CSV'
        account_code,account_name,parent_code,account_type,is_group
        6001,Online Advertising,6000,,no
        6002,Print Advertising,6000,,
        6000,Marketing Expenses,,Expense,yes
        CSV);

    $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => $file])
        ->assertCreated()
        ->assertJsonPath('data.summary', ['create' => 3, 'update' => 0, 'unchanged' => 0, 'error' => 0])
        ->assertJsonPath('data.rows.0.line', 2)
        ->assertJsonPath('data.rows.0.action', 'create');

    $child = ChartOfAccount::query()->where('account_code', '6001')->sole();
    $group = ChartOfAccount::query()->where('account_code', '6000')->sole();
    expect($child->parent_id)->toBe($group->id)
        ->and($child->account_type_id)->toBe($group->account_type_id)
        ->and($child->normal_balance)->toBe('debit')
        ->and($child->is_group)->toBeFalse()
        ->and($group->is_group)->toBeTrue();

    $log = AccountingAuditLog::query()->where('action', 'CHART_IMPORTED')->sole();
    expect($log->metadata['created'])->toEqualCanonicalizing(['6000', '6001', '6002']);
});

it('previews without saving and imports nothing when any row is wrong', function (): void {
    $file = fn () => ($this->csv)(<<<'CSV'
        account_code,account_name,parent_code,account_type
        6100,Travel,,EXPENSE
        6101,Air Tickets,9999,
        6102,,,EXPENSE
        6103,Taxis,,Nonsense
        1101,Cash In Hand,1100,ASSET
        6100,Travel again,,EXPENSE
        CSV);

    $response = $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => $file()])->assertUnprocessable();

    expect(collect($response->json('data.rows'))->mapWithKeys(fn ($row) => [$row['line'] => [$row['action'], $row['errors'][0] ?? null]])->all())->toBe([
        2 => ['create', null],
        3 => ['error', 'Parent account 9999 does not exist in the chart or in the file.'],
        4 => ['error', 'The account name is required.'],
        5 => ['error', 'Unknown account type "Nonsense" (use a code or name from Account Types).'],
        6 => ['unchanged', null],
        7 => ['error', 'Account code 6100 appears more than once in the file (first on line 2).'],
    ]);
    expect(ChartOfAccount::query()->where('account_code', '6100')->exists())->toBeFalse();

    // A clean preview saves nothing either.
    $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => ($this->csv)("account_code,account_name,account_type\n6100,Travel,EXPENSE"), 'dry_run' => true])
        ->assertOk()->assertJsonPath('data.committed', false)->assertJsonPath('data.summary.create', 1);
    expect(ChartOfAccount::query()->where('account_code', '6100')->exists())->toBeFalse();
});

it('updates existing accounts in upsert mode and leaves them alone in create mode', function (): void {
    // Blank cells (parent, control type) keep the current values.
    $csv = "account_code,account_name,parent_code,control_type,description,is_active\n5104,Stationery & Printing,,,Paper and toner,yes\n";

    $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => ($this->csv)($csv), 'mode' => 'create'])
        ->assertOk()->assertJsonPath('data.summary.unchanged', 1);
    expect(ChartOfAccount::query()->where('account_code', '5104')->value('account_name'))->toBe('Stationery Expense');

    $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => ($this->csv)($csv)])
        ->assertCreated()
        ->assertJsonPath('data.rows.0.action', 'update')
        ->assertJsonPath('data.rows.0.changes.account name', ['Stationery Expense', 'Stationery & Printing'])
        ->assertJsonPath('data.rows.0.changes.description', [null, 'Paper and toner']);
    $account = ChartOfAccount::query()->where('account_code', '5104')->sole();
    expect($account->account_name)->toBe('Stationery & Printing')
        ->and($account->parent_id)->toBe(ChartOfAccount::query()->where('account_code', '5100')->value('id'));

    // Importing the same file again changes nothing.
    $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => ($this->csv)($csv)])->assertOk()->assertJsonPath('data.summary.unchanged', 1);
});

it('keeps the chart rules: used accounts, groups and loops', function (): void {
    journal(['5104' => 10, '1101' => -10]);

    $rows = $this->import->run([
        ['account_code' => '5104', 'account_type' => 'ASSET'],                                  // used: type is locked
        ['account_code' => '5104X', 'account_name' => 'Under a posting account', 'parent_code' => '5104'],
        ['account_code' => 'A', 'account_name' => 'A', 'parent_code' => 'B', 'is_group' => 'yes'],
        ['account_code' => 'B', 'account_name' => 'B', 'parent_code' => 'A', 'is_group' => 'yes'],
        ['account_code' => '7000', 'account_name' => 'Flag', 'account_type' => 'EXPENSE', 'is_group' => 'maybe'],
    ], true)['rows'];

    expect(collect($rows)->pluck('errors.0', 'account_code')->all())->toBe([
        '5104' => 'The account type of an account with journal entries cannot be changed.',
        '5104X' => collect($rows)->firstWhere('account_code', '5104X')['errors'][0],
        'A' => 'The parent accounts in the file form a loop.',
        'B' => 'The parent accounts in the file form a loop.',
        '7000' => '"maybe" is not yes or no (is group).',
    ])->and(collect($rows)->firstWhere('account_code', '5104X')['errors'][0])->toContain('group');
    expect(ChartOfAccount::query()->where('account_code', '7000')->exists())->toBeFalse();
});

it('round-trips an export through the importer, as CSV and Excel', function (string $format): void {
    $response = $this->get("/api/v1/accounting/chart-of-accounts/export/{$format}")->assertOk();
    $content = $format === 'csv' ? $response->streamedContent() : $response->getContent();
    $path = tempnam(sys_get_temp_dir(), 'coa').'.'.$format;
    file_put_contents($path, $content);

    $rows = SpreadsheetReader::read($path, $format);
    expect(array_keys($rows[0]))->toBe(ChartOfAccountImportService::COLUMNS)
        ->and(count($rows))->toBe(ChartOfAccount::query()->count())
        ->and(collect($rows)->firstWhere('account_code', '1101'))->toMatchArray(['parent_code' => '1100', 'account_type' => 'ASSET', 'is_group' => 'no']);

    $result = $this->import->run($rows, true);
    expect($result['summary'])->toBe(['create' => 0, 'update' => 0, 'unchanged' => count($rows), 'error' => 0]);
    @unlink($path);
})->with(['csv', 'xlsx']);

it('reads semicolon CSVs with a BOM, header aliases and Windows-1252 text', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'coa');
    file_put_contents($path, "\xEF\xBB\xBFCode;Name;Parent;Type\r\n6200;Caf\xC3\xA9 Expenses;;EXPENSE\r\n\r\n");
    expect(SpreadsheetReader::read($path, 'csv'))->toBe([['code' => '6200', 'name' => 'Café Expenses', 'parent' => '', 'type' => 'EXPENSE']]);

    file_put_contents($path, "code,name\n6201,Caf\xE9\n");
    expect(SpreadsheetReader::read($path, 'csv')[0]['name'])->toBe('Café');

    $result = $this->import->run([['code' => '6200', 'name' => 'Café Expenses', 'type' => 'expense']], true);
    expect($result['summary']['create'])->toBe(1);
    @unlink($path);
});

it('refuses users without the import permission and bad files', function (): void {
    $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => ($this->csv)('x', 'chart.pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => ($this->csv)("account_code\n")])->assertUnprocessable()->assertJsonPath('message', 'The file has no accounts.');
    $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => ($this->csv)('not a workbook', 'chart.xlsx')])->assertUnprocessable()->assertJsonPath('message', 'The file is not a valid Excel (.xlsx) workbook.');

    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    Sanctum::actingAs($accountant);
    $this->postJson('/api/v1/accounting/chart-of-accounts/import', ['file' => ($this->csv)("account_code\n1")])->assertForbidden();
    $this->get('/api/v1/accounting/chart-of-accounts/export/csv')->assertOk();
});

it('imports through the React screen: preview, then confirm', function (): void {
    $this->withoutVite();

    $this->get('/accounting/chart-of-accounts/import')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/chart-of-accounts/import')->where('preview', null)->has('columns', 10));

    $redirect = $this->post('/accounting/chart-of-accounts/import/preview', ['file' => ($this->csv)("account_code,account_name,account_type\n6300,Training,EXPENSE")])
        ->assertRedirect()->headers->get('Location');
    expect(ChartOfAccount::query()->where('account_code', '6300')->exists())->toBeFalse();

    $token = null;
    $this->get($redirect)->assertInertia(function (AssertableInertia $page) use (&$token) {
        $page->where('preview.summary.create', 1)->where('preview.filename', 'chart.csv')->where('preview.rows.0.account_code', '6300');
        $token = $page->toArray()['props']['preview']['token'];
    });
    expect($token)->toBeString();

    // Another user cannot use this preview.
    $other = User::factory()->create();
    $other->assignRole('super-admin');
    $this->actingAs($other)->post('/accounting/chart-of-accounts/import', ['token' => $token])->assertSessionHas('error');

    $this->actingAs($this->owner)->post('/accounting/chart-of-accounts/import', ['token' => $token])
        ->assertRedirect('/accounting/chart-of-accounts')
        ->assertSessionHas('success', 'Chart imported: 1 created, 0 updated.');
    expect(ChartOfAccount::query()->where('account_code', '6300')->exists())->toBeTrue();

    // A preview with errors cannot be confirmed.
    $redirect = $this->post('/accounting/chart-of-accounts/import/preview', ['file' => ($this->csv)("account_code,account_name\n6400,No type")])->headers->get('Location');
    $this->get($redirect)->assertInertia(fn (AssertableInertia $page) => $page->where('preview.summary.error', 1)->where('preview.token', null));
});

it('downloads a template', function (): void {
    $csv = $this->get('/accounting/chart-of-accounts/import/template/csv')->assertOk()->streamedContent();

    expect(strtok($csv, "\n"))->toBe(implode(',', ChartOfAccountImportService::COLUMNS))
        ->and($csv)->toContain('6001,"Online Advertising",6000')
        ->and($csv)->toContain('"Marketing Expenses",,EXPENSE,');
});
