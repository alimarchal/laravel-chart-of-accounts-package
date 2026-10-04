<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Database\Seeder;

class AccountingCurrencySeeder extends Seeder
{
    public function run(): void
    {
        $baseCode = strtoupper((string) config('accounting.defaults.currency_code', 'PKR'));
        $hasBase = Currency::query()->where('is_base', true)->exists();
        $currencies = collect($this->currencies())->keyBy('code');

        // The sample exchange rates are quoted against PKR; with any other base currency
        // only the base currency itself is seeded (rates would otherwise be wrong).
        if ($baseCode !== 'PKR') {
            $currencies = collect([
                $baseCode => $currencies->get($baseCode, ['code' => $baseCode, 'name' => $baseCode, 'symbol' => $baseCode, 'exchange_rate_to_base' => 1, 'is_active' => true]),
            ]);
        }

        foreach ($currencies as $code => $currency) {
            // Never overwrite existing currencies: exchange rates and flags are user-maintained.
            if (Currency::query()->where('code', $code)->exists()) {
                continue;
            }

            $isBase = ! $hasBase && $code === $baseCode;

            Currency::query()->create(array_merge($currency, [
                'is_base' => $isBase,
                'exchange_rate_to_base' => $isBase ? 1 : $currency['exchange_rate_to_base'],
            ]));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function currencies(): array
    {
        return [
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs', 'exchange_rate_to_base' => 1, 'is_active' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate_to_base' => 280, 'is_active' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR', 'exchange_rate_to_base' => 305, 'is_active' => true],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => 'GBP', 'exchange_rate_to_base' => 355, 'is_active' => true],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'exchange_rate_to_base' => 76, 'is_active' => true],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'SAR', 'exchange_rate_to_base' => 74.5, 'is_active' => true],
        ];
    }
}
