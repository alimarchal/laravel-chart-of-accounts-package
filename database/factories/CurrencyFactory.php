<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Factories;

use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->currencyCode()),
            'name' => fake()->word(),
            'symbol' => fake()->randomElement(['$', '€', '£', '¥', '₨', '₹']),
            'exchange_rate_to_base' => fake()->randomFloat(8, 0.1, 10.0),
            'is_base' => false,
            'is_active' => true,
        ];
    }

    public function base(): static
    {
        return $this->state([
            'code' => 'PKR',
            'name' => 'Pakistani Rupee',
            'symbol' => '₨',
            'exchange_rate_to_base' => 1.0,
            'is_base' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
