<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntry;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('creates, runs and reviews a recurring entry in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);

    $this->get('/accounting')->assertSee('Recurring Entries');
    $this->get('/accounting/recurring-entries')->assertOk()->assertSee('No recurring entries yet.');
    $this->get('/accounting/recurring-entries/create')->assertOk()->assertSee('Repeats')->assertSee('5102 - Rent Expense');

    $this->post('/accounting/recurring-entries', [
        'name' => 'Office rent', 'frequency' => 'monthly', 'interval' => 1, 'start_date' => now()->toDateString(), 'mode' => 'draft',
        'lines' => [['chart_of_account_id' => account('5102')->id, 'debit' => '1500'], ['chart_of_account_id' => account('1101')->id, 'credit' => '1500']],
    ])->assertRedirect();
    $entry = RecurringEntry::query()->sole();

    $this->get("/accounting/recurring-entries/{$entry->id}")->assertOk()->assertSee('Office rent')->assertSee('Nothing generated yet.')->assertSee('Upcoming');
    $this->post("/accounting/recurring-entries/{$entry->id}/run")->assertSessionHas('success', 'Entry generated as a draft.');
    $this->get("/accounting/recurring-entries/{$entry->id}")->assertSee('draft')->assertSee('Draft #');
    $this->get("/accounting/recurring-entries/{$entry->id}/edit")->assertOk()->assertSee('Save changes');
    $this->get('/accounting/recurring-entries')->assertSee('Office rent')->assertSee('1,500.00');
});
