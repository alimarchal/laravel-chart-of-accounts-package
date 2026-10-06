<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('shows the Blade screens in Urdu, right to left, and switches back', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);

    $english = $this->get('/accounting')->assertOk()->assertSee('Journal Entries')->assertDontSee('جرنل اندراجات')->assertSee('Language');
    expect($english->getContent())->not->toContain('accounting-rtl');

    $this->post('/accounting/locale', ['locale' => 'ur'])->assertRedirect();
    $urdu = $this->get('/accounting')->assertOk()->assertSee('جرنل اندراجات')->assertSee('کھاتوں کا چارٹ')->assertSee('dir="rtl"', false)->assertSee('accounting-rtl', false);
    expect($urdu->getContent())->toContain('lang="ur"');
    // Page titles, table headings and buttons of other screens follow too.
    $this->get('/accounting/journal-entries')->assertOk()->assertSee('جرنل اندراجات')->assertSee('dir="rtl"', false);
    $this->get('/accounting/reports/trial-balance')->assertOk()->assertSee('ٹرائل بیلنس');

    $this->get('/accounting?lang=en')->assertOk()->assertSee('Journal Entries')->assertDontSee('dir="rtl"', false);
});
