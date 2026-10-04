<?php

namespace Database\Factories;

use App\Domain\Contact\Enums\PlatformType;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $platform = $this->faker->randomElement(PlatformType::cases());

        return [
            'platform' => $platform,
            'reference' => $this->referenceFor($platform),
            'is_active' => true,
        ];
    }

    private function referenceFor(PlatformType $platform): string
    {
        return match ($platform) {
            PlatformType::EMAIL => $this->faker->unique()->safeEmail(),
            PlatformType::CELLPHONE, PlatformType::WHATSAPP => $this->faker->unique()->numerify('+55##########'),
            PlatformType::URL => $this->faker->unique()->url(),
            PlatformType::YOUTUBE => '@' . $this->faker->unique()->userName(),
            PlatformType::TIKTOK => '@' . $this->faker->unique()->userName(),
            PlatformType::FACEBOOK, PlatformType::INSTAGRAM, PlatformType::TELEGRAM => $this->faker->unique()->userName(),
            PlatformType::OTHER => $this->faker->unique()->bothify('????-########'),
        };
    }
}
