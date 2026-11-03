<?php

namespace App\Application\Organization\Commands;

final readonly class UpdateOrganizationProfileCommand
{
    public function __construct(
        public int $organizationId,
        public string $profilePicture,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(int $organizationId, array $data): self
    {
        return new self(
            organizationId: $organizationId,
            profilePicture: (string) $data['profile_picture'],
        );
    }
}
