<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Alimarchal\LaravelChartOfAccounts\Tests\Support\RouteSweep;
use Alimarchal\LaravelChartOfAccounts\Tests\Support\SweepData;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    SweepData::create();
});

it('opens every React screen route without a server error', function (): void {
    $this->actingAs($this->admin);
    $failures = [];
    $urls = RouteSweep::urls('accounting');

    foreach ($urls as $url) {
        $response = $this->get($url);

        if ($response->getStatusCode() >= 500) {
            $failures[] = $response->getStatusCode().' '.$url.' '.($response->exception?->getMessage() ?? '');
        }
    }

    expect($urls)->not->toBeEmpty()->and($failures)->toBe([]);
});

it('opens every API read route without a server error', function (): void {
    Sanctum::actingAs($this->admin, ['*']);
    $failures = [];
    $urls = RouteSweep::urls('api/v1/accounting');

    foreach ($urls as $url) {
        $response = $this->getJson($url);

        if ($response->getStatusCode() >= 500) {
            $failures[] = $response->getStatusCode().' '.$url.' '.($response->exception?->getMessage() ?? '');
        }
    }

    expect($urls)->not->toBeEmpty()->and($failures)->toBe([]);
});
