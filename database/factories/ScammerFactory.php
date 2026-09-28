<?php

namespace Database\Factories;

use App\Models\Scammer;
use Database\Factories\Support\ScammerNamePool;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScammerFactory extends Factory
{
    protected $model = Scammer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(ScammerNamePool::names()),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
