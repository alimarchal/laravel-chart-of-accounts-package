<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
});

it('splits the accounting area into Accounting and Settings', function (): void {
    $this->actingAs($this->admin);

    $this->get('/accounting')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/dashboard')->where('section', 'accounting'));
    $this->get('/accounting/settings')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/dashboard')->where('section', 'settings')->where('summary', []));
})->skip(fn () => config('accounting.ui_driver') !== 'inertia', 'React screens only');

it('shows the Settings tab and its links only to people who may open something in it', function (): void {
    $role = Role::query()->firstOrCreate(['name' => 'fee-clerk', 'guard_name' => 'web']);
    $role->syncPermissions(['accounting.view', 'journal-entries.view']);
    $clerk = User::factory()->create();
    $clerk->assignRole($role);

    $this->actingAs($clerk);
    $this->get('/accounting/settings')->assertOk();                         // the page opens, but holds nothing for this person

    $manager = Role::query()->firstOrCreate(['name' => 'user-manager', 'guard_name' => 'web']);
    $manager->syncPermissions(['accounting.view', 'user.view', 'user.create', 'user.update', 'user.assign-role']);
    $boss = User::factory()->create();
    $boss->assignRole($manager);
    $this->actingAs($boss);
    $this->get('/accounting/settings')->assertOk();
})->skip(fn () => config('accounting.ui_driver') !== 'inertia', 'React screens only');

it('has the Users, Roles and Permissions cards in the Settings part of the Blade screens', function (): void {
    $this->withoutVite();
    $this->actingAs($this->admin);

    $settings = $this->get('/accounting/settings')->assertOk();
    $settings->assertSee('Users &amp; access', false)->assertSee('Roles')->assertSee('Permissions')->assertSee('Features')->assertSee('Currencies')
        ->assertSee('aria-label="Accounting sections"', false)->assertDontSee('Journal Entries');

    $this->get('/accounting')->assertOk()->assertSee('Journal Entries')->assertSee('Trial Balance')->assertDontSee('Voucher Types')->assertDontSee('Roles');
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('hides the Settings tab from a person with nothing to set up', function (): void {
    $this->withoutVite();
    $role = Role::query()->firstOrCreate(['name' => 'fee-clerk', 'guard_name' => 'web']);
    $role->syncPermissions(['accounting.view', 'journal-entries.view']);
    $clerk = User::factory()->create();
    $clerk->assignRole($role);
    $this->actingAs($clerk);

    $this->get('/accounting')->assertOk()->assertDontSee('href="'.url('/accounting/settings').'"', false);
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');
