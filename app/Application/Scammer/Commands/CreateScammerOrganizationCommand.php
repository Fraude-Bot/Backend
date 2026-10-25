<?php

namespace App\Application\Scammer\Commands;

use App\Models\Scammer;

final readonly class CreateScammerOrganizationCommand
{
    public function __construct(
        public Scammer $scammer,
        public string $name,
        public ?string $profilePicturePath,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(Scammer $scammer, array $data): self
    {
        $profilePicturePath = $data['profile_picture_path'] ?? null;

        return new self(
            scammer: $scammer,
            name: trim((string) $data['name']),
            profilePicturePath: is_string($profilePicturePath) ? $profilePicturePath : null,
        );
    }
}
