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
            'type' => fake()->randomElement(['cost_center', 'project']),
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

    /**
     * A plain cost center (department, branch …): the column allows cost_center and project.
     */
    public function department(): static
    {
        return $this->state(['type' => 'cost_center']);
    }

    public function project(): static
    {
        return $this->state(['type' => 'project']);
    }
}
