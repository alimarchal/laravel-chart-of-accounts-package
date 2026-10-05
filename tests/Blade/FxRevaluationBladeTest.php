<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\FxRevaluation;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('previews, posts, reverses and reviews a revaluation in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    account('1103')->forceFill(['currency_id' => $usd->id])->save();
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy();
    app(JournalEntryService::class)->create([
        'entry_date' => $start->copy()->addDays(10)->toDateString(), 'currency_id' => $usd->id, 'fx_rate_to_base' => '280', 'auto_post' => true,
        'lines' => [['chart_of_account_id' => account('1103')->id, 'debit' => '100'], ['chart_of_account_id' => account('4101')->id, 'credit' => '100']],
    ]);
    $asOf = $start->copy()->addDays(20)->toDateString();

    $this->get('/accounting')->assertSee('Currency Revaluation');
    $this->get('/accounting/fx-revaluation')->assertOk()->assertSee('No revaluations yet.')->assertSee('USD closing rate');
    $this->get('/accounting/fx-revaluation?'.http_build_query(['as_of_date' => $asOf, 'rates' => [$usd->id => 290]]))
        ->assertOk()->assertSee('Preview as of '.$asOf)->assertSee('29,000.00')->assertSee('1,000.00')->assertSee('Post revaluation');

    $this->post('/accounting/fx-revaluation/rates', ['currency_id' => $usd->id, 'rate_date' => $asOf, 'rate' => 290, 'source' => 'SBP'])->assertSessionHas('success');
    $this->post('/accounting/fx-revaluation', ['as_of_date' => $asOf, 'rates' => [$usd->id => 290], 'gain_loss_account_id' => account('1101')->id])->assertSessionHas('error');
    $this->post('/accounting/fx-revaluation', ['as_of_date' => $asOf, 'rates' => [$usd->id => 290], 'gain_loss_account_id' => account('4204')->id])->assertRedirect();
    $revaluation = FxRevaluation::query()->sole();

    $this->get("/accounting/fx-revaluation/{$revaluation->id}")->assertOk()->assertSee('Revaluation as of '.$asOf)->assertSee('1103 Accounts Receivable')->assertSee('not reversed');
    $this->post("/accounting/fx-revaluation/{$revaluation->id}/reverse")->assertSessionHas('success', 'Revaluation reversed.');
    $this->get('/accounting/fx-revaluation')->assertSee('SBP')->assertSee($asOf);
});
