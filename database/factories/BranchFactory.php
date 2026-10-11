<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Factories;

use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('BR-???')),
            'name' => fake()->city().' branch',
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->phoneNumber(),
            'is_head_office' => false,
            'is_active' => true,
        ];
    }

    public function headOffice(): static
    {
        return $this->state(['is_head_office' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
