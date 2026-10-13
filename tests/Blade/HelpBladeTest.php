<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->makeUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('serves the help centre: the index grouped like the menu, an article, and not found for anything else', function (): void {
    $this->actingAs(($this->makeUser)('viewer'));

    $this->get('/accounting/help')->assertOk()->assertSee('Getting started')->assertSee('Journal entries')->assertSee('Trial balance')
        ->assertDontSee('Modules on / off');                                                      // a viewer has no settings
    $this->get('/accounting/help/journal-entries')->assertOk()->assertSee('What it is')->assertSee('Journal entries')->assertSee('How to record an entry');
    $this->get('/accounting/help/no-such-topic')->assertNotFound();
});

it('gives the owner the settings articles too', function (): void {
    $this->actingAs(($this->makeUser)('super-admin'));

    $this->get('/accounting/help')->assertOk()->assertSee('Modules on / off')->assertSee('Account roles');
});

it('puts a Help button on a screen that has an article, and none on the help pages', function (): void {
    $this->actingAs(($this->makeUser)('super-admin'));

    $this->get('/accounting/payroll')->assertOk()->assertSee('href="/accounting/help/payroll"', false)->assertSee('Help: Payroll runs');
    $this->get('/accounting/help/payroll')->assertOk()->assertDontSee('Help: Payroll runs');
});

it('draws the menu of the accounting area in the Flux sidebar and the top navigation components', function (): void {
    $this->actingAs(($this->makeUser)('super-admin'));
    $user = auth()->user();

    $mega = (string) view('accounting::components.menu.dropdown', ['kit' => 'jetstream'])->render();
    $responsive = (string) view('accounting::components.menu.responsive')->render();

    expect($mega)->toContain('acc-mega')->toContain('Journal entries')->toContain('Payroll runs')->toContain('Accounting settings')->toContain('Open the help centre')
        ->and($responsive)->toContain('acc-rmenu')->toContain('Trial balance')
        ->and($user)->not->toBeNull();
});
