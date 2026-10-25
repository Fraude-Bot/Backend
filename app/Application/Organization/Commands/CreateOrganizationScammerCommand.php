<?php

namespace App\Application\Organization\Commands;

use App\Models\Organization;

final readonly class CreateOrganizationScammerCommand
{
    public function __construct(
        public Organization $organization,
        public string $name,
        public ?string $profilePicturePath,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(Organization $organization, array $data): self
    {
        $profilePicturePath = $data['profile_picture_path'] ?? null;

        return new self(
            organization: $organization,
            name: trim((string) $data['name']),
            profilePicturePath: is_string($profilePicturePath) ? $profilePicturePath : null,
        );
    }
}
