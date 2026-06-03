<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Illuminate\Database\Seeder;

class AccountingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccountingPermissionSeeder::class,
            AccountingAccountTypeSeeder::class,
            AccountingCurrencySeeder::class,
            AccountingPeriodSeeder::class,
            AccountingChartOfAccountSeeder::class,
            AccountingCostCenterSeeder::class,
            AccountingTaxCodeSeeder::class,
            AccountingTaxRateSeeder::class,
        ]);
    }
}
