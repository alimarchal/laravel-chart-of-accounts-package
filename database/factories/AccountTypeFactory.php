<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Factories;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountType>
 */
class AccountTypeFactory extends Factory
{
    protected $model = AccountType::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->words(2, true),
            'normal_balance' => fake()->randomElement(['debit', 'credit']),
            'report_group' => fake()->randomElement(['BalanceSheet', 'IncomeStatement']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function balanceSheet(): static
    {
        return $this->state(['report_group' => 'BalanceSheet']);
    }

    public function incomeStatement(): static
    {
        return $this->state(['report_group' => 'IncomeStatement']);
    }

    public function debitNormal(): static
    {
        return $this->state(['normal_balance' => 'debit']);
    }

    public function creditNormal(): static
    {
        return $this->state(['normal_balance' => 'credit']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
