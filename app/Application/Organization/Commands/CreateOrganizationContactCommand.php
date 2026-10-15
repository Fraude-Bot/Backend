<?php

namespace App\Application\Organization\Commands;

use App\Domain\Contact\Enums\PlatformType;
use App\Models\Organization;

final readonly class CreateOrganizationContactCommand
{
    public function __construct(
        public Organization $organization,
        public PlatformType $platform,
        public string $reference,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(Organization $organization, array $data): self
    {
        return new self(
            organization: $organization,
            platform: PlatformType::from((int) $data['platform']),
            reference: (string) $data['reference'],
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
