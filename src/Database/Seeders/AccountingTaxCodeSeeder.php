<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Illuminate\Database\Seeder;

class AccountingTaxCodeSeeder extends Seeder
{
    public function run(): void
    {
        TaxCode::query()->updateOrCreate(
            ['code' => 'EXEMPT'],
            ['name' => 'Tax Exempt', 'description' => 'Default zero-rate tax code.', 'is_active' => true]
        );
    }
}
