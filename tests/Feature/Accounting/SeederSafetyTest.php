<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;

it('seeds the industry-neutral chart by default', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    expect(account('4101')->account_name)->toBe('Sales Revenue')
        ->and(account('1104')->account_name)->toBe('Other Receivables');
});

it('seeds the school chart when that preset is configured', function (): void {
    config(['accounting.chart_preset' => 'school']);

    $this->seed(AccountingDatabaseSeeder::class);

    expect(account('4101')->account_name)->toBe('Tuition Fee Income');
});

it('never overwrites accounts or currencies the user has changed', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    account('5104')->update(['account_name' => 'Renamed', 'is_active' => false]);
    Currency::query()->where('code', 'USD')->update(['exchange_rate_to_base' => 300]);

    $this->seed(AccountingDatabaseSeeder::class);

    expect(account('5104')->account_name)->toBe('Renamed')
        ->and(account('5104')->is_active)->toBeFalse()
        ->and((float) Currency::query()->where('code', 'USD')->value('exchange_rate_to_base'))->toBe(300.0);
});

it('uses the configured base currency', function (): void {
    config(['accounting.defaults.currency_code' => 'USD']);

    $this->seed(AccountingDatabaseSeeder::class);

    expect(Currency::query()->where('is_base', true)->pluck('code')->all())->toBe(['USD'])
        ->and(Currency::query()->count())->toBe(1);
});
