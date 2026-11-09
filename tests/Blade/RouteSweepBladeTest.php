<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Alimarchal\LaravelChartOfAccounts\Tests\Support\RouteSweep;
use Alimarchal\LaravelChartOfAccounts\Tests\Support\SweepData;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $this->actingAs($admin);
    SweepData::create();
});

it('opens every Blade screen route without an error', function (): void {
    $urls = [...RouteSweep::urls('accounting'), ...RouteSweep::urls('settings')];

    expect($urls)->not->toBeEmpty()->and(RouteSweep::failures($this, $urls))->toBe([]);
});

it('knows which Blade routes it cannot reach, so a new route cannot be skipped unnoticed', function (): void {
    expect([...RouteSweep::skipped('accounting'), ...RouteSweep::skipped('settings')])->toBe(SweepData::UNREACHED_BLADE);
});
