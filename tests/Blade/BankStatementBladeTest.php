<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatement;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatementLine;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Http\UploadedFile;

it('imports, matches, books and reconciles a bank statement in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
    $bank = BankAccount::query()->create(['chart_of_account_id' => account('1108')->id, 'account_name' => 'Operating', 'account_number' => 'PK00-1234']);
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy();
    $d = fn (int $n) => $start->copy()->addDays($n)->toDateString();
    journal(['1108' => 5000, '4101' => -5000], $d(10));

    $this->get('/accounting')->assertSee('Bank Statements');
    $this->get('/accounting/bank-statements')->assertOk()->assertSee('No statements imported yet.');
    $this->get('/accounting/bank-statements/import')->assertOk()->assertSee('Operating')->assertSee('Preview');

    $csv = "Date,Description,Reference,Withdrawal,Deposit,Balance\n{$d(10)},Customer payment,CHQ1,,5000.00,5000.00\n{$d(11)},Bank charges,,25.50,,4974.50\nnot-a-date,Broken,,,1,\n";
    $response = $this->post('/accounting/bank-statements/import/preview', ['bank_account_id' => $bank->id, 'file' => UploadedFile::fake()->createWithContent('s.csv', $csv)]);
    $response->assertRedirect();
    $this->get($response->headers->get('Location'))->assertOk()->assertSee('1 with errors')->assertSee('Some rows have errors');

    $csv = "Date,Description,Reference,Withdrawal,Deposit,Balance\n{$d(10)},Customer payment,CHQ1,,5000.00,5000.00\n{$d(11)},Bank charges,,25.50,,4974.50\n";
    $location = $this->post('/accounting/bank-statements/import/preview', ['bank_account_id' => $bank->id, 'file' => UploadedFile::fake()->createWithContent('s.csv', $csv)])->headers->get('Location');
    $this->get($location)->assertOk()->assertSee('2 new')->assertSee('Import 2 transactions');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->post('/accounting/bank-statements/import', ['token' => $query['preview'], 'closing_balance' => '4974.50'])->assertRedirect();
    $statement = BankStatement::query()->sole();

    $this->get("/accounting/bank-statements/{$statement->id}")->assertOk()->assertSee('Customer payment')->assertSee('Auto-match')->assertSee('2 to match');
    $this->post("/accounting/bank-statements/{$statement->id}/auto-match")->assertSessionHas('success', '1 transactions matched.');
    $fee = BankStatementLine::query()->where('description', 'Bank charges')->sole();
    $this->get("/accounting/bank-statement-lines/{$fee->id}/candidates", ['Accept' => 'application/json'])->assertOk()->assertJsonCount(0, 'data');
    $this->post("/accounting/bank-statement-lines/{$fee->id}/create-entry", ['chart_of_account_id' => account('5102')->id])->assertSessionHas('success', 'Entry posted and matched.');
    $this->get("/accounting/bank-statements/{$statement->id}")->assertSee('matched')->assertSee('created');
    $this->post("/accounting/bank-statements/{$statement->id}/reconcile")->assertSessionHas('success', 'Statement reconciled.');
    $this->get('/accounting/bank-statements')->assertSee('reconciled')->assertSee('4,974.50');
    $this->get('/accounting/bank-statements')->assertDontSee('to match');
});
