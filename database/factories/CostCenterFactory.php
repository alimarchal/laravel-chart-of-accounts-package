<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Factories;

use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'code' => strtoupper(fake()->unique()->lexify('CC-???')),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['department', 'project', 'branch']),
            'description' => fake()->sentence(),
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function department(): static
    {
        return $this->state(['type' => 'department']);
    }

    public function project(): static
    {
        return $this->state(['type' => 'project']);
    }
}
