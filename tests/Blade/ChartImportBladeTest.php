<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('imports a chart from the Blade screens', function (): void {
    $this->get('/accounting/chart-of-accounts')->assertOk()
        ->assertSee('/accounting/chart-of-accounts/export/xlsx', false)
        ->assertSee('/accounting/chart-of-accounts/import', false);

    $file = UploadedFile::fake()->createWithContent('chart.csv', "account_code,account_name,account_type,description\n6500,Repairs,EXPENSE,\n5104,Stationery & Printing,,Paper\n");
    $location = $this->post('/accounting/chart-of-accounts/import/preview', ['file' => $file])->assertRedirect()->headers->get('Location');

    $page = $this->get($location)->assertOk()
        ->assertSee('1 new')->assertSee('1 updated')
        ->assertSee('Stationery Expense')   // the old name, struck through
        ->assertSee('Import 2 accounts');
    preg_match('/name="token" value="([^"]+)"/', $page->getContent(), $match);

    $this->post('/accounting/chart-of-accounts/import', ['token' => $match[1]])
        ->assertRedirect('/accounting/chart-of-accounts')
        ->assertSessionHas('success', 'Chart imported: 1 created, 1 updated.');
    expect(ChartOfAccount::query()->where('account_code', '6500')->exists())->toBeTrue()
        ->and(ChartOfAccount::query()->where('account_code', '5104')->value('description'))->toBe('Paper');

    // A file the reader cannot use comes back as a message.
    $this->from('/accounting/chart-of-accounts/import')
        ->post('/accounting/chart-of-accounts/import/preview', ['file' => UploadedFile::fake()->createWithContent('chart.xlsx', 'nope')])
        ->assertRedirect('/accounting/chart-of-accounts/import')
        ->assertSessionHas('error', 'The file is not a valid Excel (.xlsx) workbook.');
});
