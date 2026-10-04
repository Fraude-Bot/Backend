<?php

namespace Database\Factories;

use App\Models\Product;
use Database\Factories\Support\ScamEnterprisePool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $market = fake()->randomElement(ScamEnterprisePool::markets());

        return [
            'name' => fake()->unique()->numerify($market.'-####'),
        ];
    }
}
