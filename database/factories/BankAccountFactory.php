<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Factories;

use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'chart_of_account_id' => ChartOfAccountFactory::new()->debitNormal()->posting(),
            'account_name' => fake()->company().' Bank',
            'account_number' => fake()->numerify('####-####-####'),
            'bank_name' => fake()->randomElement(['HBL', 'MCB', 'UBL', 'ABL', 'Meezan Bank', 'Allied Bank']),
            'branch' => fake()->city(),
            'iban' => 'PK'.fake()->numerify('##XXXX################'),
            'swift_code' => strtoupper(fake()->lexify('????PK??')),
            'is_active' => true,
            'description' => fake()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
