<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('runs month-end and year-end close from the Blade screens', function (): void {
    $year = now()->year + 1;
    $this->post('/accounting/periods/generate-monthly', ['start_date' => "{$year}-01-01"])->assertSessionHas('success');
    $this->get('/accounting/periods')->assertSee("January {$year}")->assertSee('Close…');

    $january = AccountingPeriod::query()->where('name', "January {$year}")->firstOrFail();
    journal(['1101' => 75, '4101' => -75], "{$year}-01-05", post: false);

    $this->get("/accounting/periods/{$january->id}/close")
        ->assertSuccessful()->assertSee('Month-end close')->assertSee('No draft entries in the period')->assertSee('Fix the items marked');

    $this->post("/accounting/periods/{$january->id}/close")->assertSessionHas('error');

    // Year-end close of the seeded (calendar) year.
    $current = AccountingPeriod::query()->whereDate('start_date', now()->startOfYear())->firstOrFail();
    journal(['1101' => 500, '4101' => -500]);
    $this->get("/accounting/periods/{$current->id}/close?year_end=1")->assertSee('Closing entry preview')->assertSee('500.00');
    $this->post("/accounting/periods/{$current->id}/close-fiscal-year")->assertSessionHas('success');
    expect($current->fresh()->status)->toBe('closed');

    $this->get("/accounting/periods/{$current->id}/close")->assertSee('Reason for reopening');
    $this->post("/accounting/periods/{$current->id}/reopen", ['reason' => 'Auditor adjustment'])->assertSessionHas('success');
    expect($current->fresh()->status)->toBe('open');
});
