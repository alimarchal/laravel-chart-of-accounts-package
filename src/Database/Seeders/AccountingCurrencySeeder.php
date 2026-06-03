<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Database\Seeder;

class AccountingCurrencySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->currencies() as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function currencies(): array
    {
        return [
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs', 'exchange_rate_to_base' => 1, 'is_base' => true, 'is_active' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_base' => 280, 'is_base' => false, 'is_active' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR', 'exchange_rate_to_base' => 305, 'is_base' => false, 'is_active' => true],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => 'GBP', 'exchange_rate_to_base' => 355, 'is_base' => false, 'is_active' => true],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'exchange_rate_to_base' => 76, 'is_base' => false, 'is_active' => true],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'SAR', 'exchange_rate_to_base' => 74.5, 'is_base' => false, 'is_active' => true],
        ];
    }
}
