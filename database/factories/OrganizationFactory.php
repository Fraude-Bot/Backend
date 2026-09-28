<?php

namespace Database\Factories;

use App\Models\Organization;
use Database\Factories\Support\ScamEnterprisePool;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(ScamEnterprisePool::companyNames()),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
