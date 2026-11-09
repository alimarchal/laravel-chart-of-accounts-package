<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Alimarchal\LaravelChartOfAccounts\Tests\Support\RouteSweep;
use Alimarchal\LaravelChartOfAccounts\Tests\Support\SweepData;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    SweepData::create();
});

it('opens every React screen route without an error', function (): void {
    $this->actingAs($this->admin);
    $urls = RouteSweep::urls('accounting');

    expect($urls)->not->toBeEmpty()->and(RouteSweep::failures($this, $urls))->toBe([]);
});

it('opens every API read route without an error', function (): void {
    Sanctum::actingAs($this->admin, ['*']);
    // The API throttles a client; a sweep is hundreds of requests from one.
    $this->withoutMiddleware(ThrottleRequests::class);
    $urls = RouteSweep::urls('api/v1/accounting');

    expect($urls)->not->toBeEmpty()->and(RouteSweep::failures($this, $urls, json: true))->toBe([]);
});

it('knows which routes it cannot reach, so a new route cannot be skipped unnoticed', function (): void {
    // Routes of records the sweep does not create (see SweepData). Add the record, or the route here, when this list changes.
    expect(RouteSweep::skipped('accounting'))->toBe(SweepData::UNREACHED_REACT)
        ->and(RouteSweep::skipped('api/v1/accounting'))->toBe(SweepData::UNREACHED_API);
});
