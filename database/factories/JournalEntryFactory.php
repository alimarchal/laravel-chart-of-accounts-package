<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Factories;

use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'entry_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'accounting_period_id' => null,
            'currency_id' => fn () => Currency::firstOrCreate(['code' => 'PKR'], ['name' => 'Pakistani Rupee', 'symbol' => '₨', 'exchange_rate_to_base' => 1.0, 'is_base' => true, 'is_active' => true]),
            'fx_rate_to_base' => 1.0,
            'reference' => strtoupper(fake()->lexify('JNL-??????')),
            'description' => fake()->sentence(),
            'status' => 'draft',
            'posted_at' => null,
            'posted_by' => null,
            'reverses_entry_id' => null,
            'reversed_by_entry_id' => null,
            'reversed_at' => null,
            'is_closing_entry' => false,
            'closes_period_id' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state([
            'status' => 'draft',
            'posted_at' => null,
            'posted_by' => null,
        ]);
    }

    public function posted(): static
    {
        return $this->state([
            'status' => 'posted',
            'posted_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state([
            'status' => 'void',
            'posted_at' => null,
        ]);
    }

    public function closingEntry(): static
    {
        return $this->state(['is_closing_entry' => true]);
    }
}
