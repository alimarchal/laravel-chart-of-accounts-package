<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Factories;

use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reconciliation>
 */
class ReconciliationFactory extends Factory
{
    protected $model = Reconciliation::class;

    public function definition(): array
    {
        $statementBalance = fake()->randomFloat(2, 1000, 100000);

        return [
            'bank_account_id' => BankAccountFactory::new(),
            'statement_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'statement_balance' => $statementBalance,
            'book_balance' => 0,
            'status' => 'draft',
            'completed_at' => null,
            'completed_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state([
            'status' => 'draft',
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
