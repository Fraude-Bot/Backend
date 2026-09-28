<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    private static int $monthOffset = 0;

    public function definition(): array
    {
        $latest = now()->startOfMonth();
        $earliest = now()->setYear(2020)->startOfYear();
        $monthCount = (int) $earliest->diffInMonths($latest) + 1;

        $createdAt = $latest->copy()->subMonths(self::$monthOffset % $monthCount);
        $createdAt = $createdAt
            ->setDay(fake()->numberBetween(1, $createdAt->daysInMonth))
            ->setTime(
                fake()->numberBetween(8, 20),
                fake()->numberBetween(0, 59),
                fake()->numberBetween(0, 59),
            );

        self::$monthOffset++;

        return [
            'user_id' => User::factory(),
            'title' => substr($this->faker->sentence(3), 0, 50),
            'description' => $this->faker->paragraph(),
            'is_active' => true,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
