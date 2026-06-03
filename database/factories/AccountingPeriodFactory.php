<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Factories;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingPeriod>
 */
class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2023, 2026);
        $month = fake()->numberBetween(1, 12);
        $start = \Carbon\Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        return [
            'name' => $start->format('F Y'),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
            'closing_journal_entry_id' => null,
            'closing_total_debits' => null,
            'closing_total_credits' => null,
            'closing_net_income' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => 'open', 'closed_at' => null]);
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed', 'closed_at' => now()]);
    }

    public function forCurrentMonth(): static
    {
        return $this->state([
            'name' => now()->format('F Y'),
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);
    }
}
