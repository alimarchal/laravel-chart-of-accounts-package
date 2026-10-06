<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Models\TaxReturn;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('sets up tax, books taxed documents and files a return in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $d = fn (int $day) => $start->copy()->addDays($day - 1)->toDateString();

    $this->get('/accounting')->assertSee('Tax');
    $this->get('/accounting/tax-codes/create')->assertOk()->assertSee('Tax account')->assertSee('Kind');
    $this->post('/accounting/tax-codes', ['code' => 'GST17', 'name' => 'GST 17%', 'kind' => 'output', 'tax_account_id' => account('2104')->id, 'jurisdiction' => 'FBR', 'is_active' => 1])->assertRedirect();
    $this->post('/accounting/tax-codes', ['code' => 'GSTIN', 'name' => 'GST input', 'kind' => 'input', 'tax_account_id' => account('1107')->id, 'is_active' => 1])->assertRedirect();
    foreach (TaxCode::query()->whereIn('code', ['GST17', 'GSTIN'])->get() as $code) {
        TaxRate::query()->create(['tax_code_id' => $code->id, 'rate' => '17', 'effective_from' => $start->copy()->subYear()->toDateString()]);
    }
    $out = TaxCode::query()->where('code', 'GST17')->firstOrFail();
    $in = TaxCode::query()->where('code', 'GSTIN')->firstOrFail();

    $this->get('/accounting/tax')->assertOk()->assertSee('GST17')->assertSee('17%')->assertSee('FBR')->assertSee('No returns filed yet.')->assertSee('Calculator');
    $this->get('/accounting/tax/entries/create')->assertOk()->assertSee('Sale (invoice)')->assertSee('Payment with tax withheld');
    $this->post('/accounting/tax/entries', ['type' => 'sale', 'entry_date' => $d(5), 'amount' => '1000', 'tax_code_id' => $out->id, 'account_id' => account('4101')->id, 'counter_account_id' => account('1103')->id, 'reference' => 'INV-1', 'auto_post' => 1])->assertRedirect();
    $this->post('/accounting/tax/entries', ['type' => 'purchase', 'entry_date' => $d(6), 'amount' => '400', 'tax_code_id' => $in->id, 'account_id' => account('5102')->id, 'counter_account_id' => account('2101')->id, 'auto_post' => 1])->assertRedirect();
    $this->post('/accounting/tax/entries', ['type' => 'sale', 'entry_date' => $d(6), 'amount' => '10', 'tax_code_id' => $in->id, 'account_id' => account('4101')->id, 'counter_account_id' => account('1103')->id])->assertSessionHas('error');
    expect(JournalEntry::query()->where('status', 'posted')->count())->toBe(2);

    $this->get('/accounting/tax/returns/report?date_from='.$d(1).'&date_to='.$d(28))->assertOk()->assertSee('GST17')->assertSee('INV-1')->assertSee('170.00')->assertSee('68.00')->assertSee('102.00');
    $this->post('/accounting/tax/returns', ['period_from' => $d(1), 'period_to' => $d(28), 'payable_account_id' => account('2102')->id, 'reference' => 'OCT'])->assertRedirect()->assertSessionHas('success');
    $this->get('/accounting/tax')->assertSee('OCT')->assertSee('102.00');
    $this->post('/accounting/tax/returns', ['period_from' => $d(1), 'period_to' => $d(28), 'payable_account_id' => account('2102')->id])->assertSessionHas('error');

    $this->post('/accounting/tax/calculate', ['tax_code_id' => $out->id, 'amount' => 1170, 'inclusive' => true, 'date' => $d(5)], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.tax', '170.00');
    $this->delete('/accounting/tax/returns/'.TaxReturn::query()->value('id'))->assertSessionHas('success');
    expect(TaxReturn::query()->count())->toBe(0);
});
