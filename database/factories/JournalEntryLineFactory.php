<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Factories;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntryLine>
 */
class JournalEntryLineFactory extends Factory
{
    protected $model = JournalEntryLine::class;

    public function definition(): array
    {
        $isDebit = fake()->boolean();
        $amount = fake()->randomFloat(2, 10, 10000);

        return [
            'journal_entry_id' => JournalEntryFactory::new(),
            'line_no' => fake()->numberBetween(1, 10),
            'chart_of_account_id' => ChartOfAccountFactory::new()->posting(),
            'cost_center_id' => null,
            'debit' => $isDebit ? $amount : 0,
            'credit' => $isDebit ? 0 : $amount,
            'description' => fake()->sentence(),
            'reconciliation_status' => 'unreconciled',
            'reconciliation_id' => null,
            'reconciled_at' => null,
            'reconciled_by' => null,
        ];
    }

    public function debit(float $amount): static
    {
        return $this->state(['debit' => $amount, 'credit' => 0]);
    }

    public function credit(float $amount): static
    {
        return $this->state(['debit' => 0, 'credit' => $amount]);
    }

    public function reconciled(): static
    {
        return $this->state([
            'reconciliation_status' => 'reconciled',
            'reconciled_at' => now(),
        ]);
    }

    public function unreconciled(): static
    {
        return $this->state([
            'reconciliation_status' => 'unreconciled',
            'reconciliation_id' => null,
            'reconciled_at' => null,
        ]);
    }
}
