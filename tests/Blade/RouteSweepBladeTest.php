<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Alimarchal\LaravelChartOfAccounts\Tests\Support\RouteSweep;
use Alimarchal\LaravelChartOfAccounts\Tests\Support\SweepData;

it('opens every Blade screen route without a server error', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    $this->actingAs($admin);
    SweepData::create();
    $failures = [];
    $urls = [...RouteSweep::urls('accounting'), ...RouteSweep::urls('settings')];

    foreach ($urls as $url) {
        $response = $this->get($url);

        if ($response->getStatusCode() >= 500) {
            $failures[] = $response->getStatusCode().' '.$url.' '.($response->exception?->getMessage() ?? '');
        }
    }

    expect($urls)->not->toBeEmpty()->and($failures)->toBe([]);
});
